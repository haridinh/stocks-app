<?php

namespace App\Http\Controllers\Backend; // Đặt namespace cho controller này vào nhóm Backend

use App\Helpers\Helper\Helper; // Sử dụng Helper từ thư mục Helpers để làm việc với các chức năng tiện ích
use App\Http\Controllers\Controller; // Kế thừa controller cơ bản của Laravel
use App\Http\Requests\ManualGatewayRequest; // Sử dụng request để xác thực các dữ liệu đầu vào cho việc tạo mới và cập nhật gateway
use App\Models\{  // Sử dụng nhiều model trong ứng dụng
    Admin,  // Quản lý thông tin admin
    Configuration,  // Cấu hình ứng dụng
    Deposit,  // Quản lý giao dịch nạp tiền
    Gateway,  // Cổng thanh toán
    GeneralSetting,  // Cài đặt chung của ứng dụng
    Payment,  // Quản lý thanh toán
    PlanSubscription,  // Quản lý đăng ký gói
    Transaction,  // Giao dịch
    User  // Quản lý người dùng
};
use App\Notifications\PlanSubscriptionNotification; // Thông báo về đăng ký gói
use App\Services\ManualGatewayService; // Dịch vụ xử lý các thao tác với gateway
use Illuminate\Http\Request; // Lớp Request của Laravel để xử lý các yêu cầu HTTP
use Illuminate\Support\Str; // Hỗ trợ xử lý chuỗi
use Illuminate\Validation\Rule; // Quy tắc xác thực
use DB; // Truy vấn cơ sở dữ liệu trực tiếp

class ManageGatewayController extends Controller // Controller xử lý các yêu cầu liên quan đến cổng thanh toán
{
    protected $gateway; // Khai báo thuộc tính gateway để sử dụng trong toàn bộ controller

    public function __construct(ManualGatewayService $gateway) // Khởi tạo controller và inject dịch vụ ManualGatewayService
    {
        $this->gateway = $gateway;
    }

    // Hiển thị danh sách các cổng thanh toán online
    public function online()
    {
        $data['title'] = 'Online payment gateways';  // Tiêu đề trang

        // Lấy danh sách các cổng thanh toán online (type = true)
        $data['gateways'] = Gateway::where('type', true)->latest()->get();

        // Trả về view với dữ liệu đã chuẩn bị
        return view('backend.gateway.index')->with($data);
    }

    // Hiển thị danh sách các cổng thanh toán offline
    public function offline()
    {
        $data['title'] = 'Offline payment gateways';  // Tiêu đề trang

        // Lấy danh sách các cổng thanh toán offline (type = false)
        $data['gateways'] = Gateway::where('type', false)->latest()->get();

        // Trả về view với dữ liệu đã chuẩn bị
        return view('backend.gateway.index')->with($data);
    }

    // Tải và hiển thị view cho cổng thanh toán cụ thể (theo tên)
    public function loadView($view)
    {
        $data['title'] = ucfirst($view) . ' Payment';  // Tiêu đề trang theo tên view

        // Lấy thông tin cổng thanh toán từ tên
        $data['gateway'] = Gateway::where('name', $view)->first();

        // Nếu cổng thanh toán là GoUrl, hiển thị trang GoUrl
        if (str_contains($view, 'gourl')) {
            $data['title'] = 'GoUrl Payment';  // Tiêu đề trang GoUrl

            $data['currency'] = config('laravel-crypto-payment-gateway.paymentbox'); // Lấy cấu hình tiền tệ từ config

            // Lấy tất cả các cổng thanh toán có tên chứa 'gourl'
            $data['gateways'] = Gateway::where('name', 'LIKE', '%' . 'gourl' . '%')->get();

            return view('backend.gateway.gourl')->with($data);  // Trả về view GoUrl
        }

        // Nếu không phải GoUrl, trả về view mặc định theo tên view
        $loadView = 'backend.gateway.' . $view;
        return view($loadView)->with($data);  // Trả về view theo tên
    }

    // Cập nhật trạng thái của một cổng thanh toán
    public function status($id)
    {
        $gateway = Gateway::find($id);  // Lấy thông tin cổng thanh toán theo ID

        if (!$gateway) {
            return response()->json(['success' => false]);  // Nếu không tìm thấy cổng thanh toán, trả về lỗi
        }

        $gateway->status = !$gateway->status;  // Đảo trạng thái cổng thanh toán
        $gateway->save();  // Lưu trạng thái mới

        return response()->json(['success' => true]);  // Trả về kết quả thành công
    }

    // Cập nhật cổng thanh toán online
    public function updateOnlinePaymentGateway(Request $request, $id)
    {
        $gateway = Gateway::findOrFail($id);  // Lấy cổng thanh toán theo ID, nếu không tìm thấy thì lỗi 404

        // Xác thực dữ liệu đầu vào
        $request->validate([
            "parameter.*" => 'required',
            'image' => [Rule::requiredIf(function () use ($gateway) {
                return $gateway == null;  // Nếu không có gateway, yêu cầu upload ảnh
            }), 'image', 'mimes:jpg,png,jpeg'],
            'rate' => 'required|numeric',  // Tỉ lệ phải là số
        ]);

        // Gọi dịch vụ để cập nhật cổng thanh toán online
        $isSuccess = $this->gateway->updateOnlineGateway($request, $gateway);

        if ($isSuccess['type'] == 'success') {
            return redirect()->back()->with('success', 'Gateway Updated Successfully');  // Nếu thành công, quay lại và thông báo
        }
    }

    // Cập nhật cổng thanh toán GoUrl
    public function gourlUpdate(Request $request)
    {
        // Xác thực dữ liệu đầu vào cho cổng GoUrl
        $request->validate([
            'parameter' => 'required|array',
            'parameter.*.gateway_currency' => 'required',
            'parameter.*.public_key' => 'required',
            'parameter.*.private_key' => 'required',
            'parameter.*.rate' => 'required|gt:0',
            'parameter.*.gourl_image' => 'sometimes|mimes:jpg,png,jpeg',
            'status' => 'parameter.*.required'
        ]);

        // Lặp qua từng tham số của cổng GoUrl và cập nhật
        foreach ($request->parameter as $key =>  $params) {

            $gatewayName = 'gourl_' . $key;  // Tạo tên gateway từ key

            // Tìm cổng thanh toán theo tên
            $gateway = Gateway::where('name', $gatewayName)->first();

            if ($gateway) {  // Nếu cổng thanh toán đã tồn tại
                // Cập nhật hình ảnh mới nếu có
                if (isset($params['image'])) {
                    $image = Helper::saveImage($params['image'], Helper::filePath('gateways', true), '200x200', $gateway->image);
                } else {
                    $image = $gateway->image;  // Giữ nguyên hình ảnh cũ nếu không có hình ảnh mới
                }

                // Cập nhật thông số của cổng thanh toán
                $gatewayParameters = [
                    'gateway_currency' => $params['gateway_currency'],
                    'public_key' => $params['public_key'],
                    'private_key' => $params['private_key'],
                ];
            } else {  // Nếu cổng thanh toán chưa tồn tại, tạo mới
                $gateway = new Gateway();

                $gatewayParameters = [
                    'gateway_currency' => $params['gateway_currency'],
                    'public_key' => $params['public_key'],
                    'private_key' => $params['private_key'],
                ];

                // Lưu hình ảnh mới
                $image = Helper::saveImage($params['image'], Helper::filePath('gateways', true));
            }

            // Lưu hoặc cập nhật thông tin cổng thanh toán
            $gateway->name = $gatewayName;
            $gateway->image = $image;
            $gateway->parameter = $gatewayParameters;
            $gateway->rate = $params['rate'];
            $gateway->type = 1;  // Cổng thanh toán online
            $gateway->save();  // Lưu cổng thanh toán

            // Cập nhật biến môi trường
            Helper::setEnv([
                strtoupper($gatewayName) . '_PUBLIC_KEY' => $gatewayParameters['public_key'],
                strtoupper($gatewayName) . '_PRIVATE_KEY' => $gatewayParameters['private_key'],
            ]);
        }

        // Trả về thông báo thành công
        return redirect()->back()->with('success', 'Gateway Updated Successfully');
    }

    // Tạo cổng thanh toán offline mới
    public function offlineCreate()
    {
        $data['title'] = 'Create Gateway';  // Tiêu đề trang

        // Trả về view tạo cổng thanh toán offline
        return view('backend.gateway.create_bank')->with($data);
    }

    // Lưu cổng thanh toán offline mới
    public function offlineStore(ManualGatewayRequest $request)
    {
        // Gọi dịch vụ để tạo cổng thanh toán mới
        $isSuccess = $this->gateway->createMethod($request);

        if ($isSuccess['type'] == 'success') {
            return redirect()->back()->with('success', $isSuccess['message']);  // Thông báo thành công
        }
    }

    // Chỉnh sửa cổng thanh toán offline
    public function offlineEdit($id)
    {
        $data['title'] = 'Offline Payment Gateway';  // Tiêu đề trang

        // Lấy thông tin cổng thanh toán offline theo ID
        $data['gateway'] = Gateway::findOrFail($id);

        // Trả về view chỉnh sửa cổng thanh toán offline
        return view('backend.gateway.bank')->with($data);
    }

    // Cập nhật cổng thanh toán offline
    public function offlineUpdate(ManualGatewayRequest $request, $id)
    {
        $gateway = Gateway::findOrFail($id);  // Lấy thông tin cổng thanh toán theo ID

        // Cập nhật cổng thanh toán offline thông qua dịch vụ
        if ($this->gateway->updateMethod($request, $gateway)['type'] == 'success')
            return redirect()->back()->with('success', "Payment Gateway Setting Updated Successfully");  // Thông báo thành công
    }
}
