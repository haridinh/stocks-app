<?php

namespace App\Http\Controllers;

// Import các lớp cần thiết
use App\Helpers\Helper\Helper;
use App\Http\Requests\PaymentRequest;
use App\Models\Deposit;
use App\Models\Gateway;
use App\Models\Payment;
use App\Models\Plan;
use App\Services\Gateway\Gourl;
use App\Services\Gateway\Manual;
use App\Services\PaymentService;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    // Khai báo thuộc tính payment để lưu trữ dịch vụ thanh toán
    protected $payment;

    // Khởi tạo controller với PaymentService
    public function __construct(PaymentService $payment)
    {
        $this->payment = $payment; // Gán dịch vụ thanh toán vào thuộc tính
    }

    // Hiển thị các phương thức thanh toán (gateway) cho một kế hoạch cụ thể
    public function gateways(Request $request, $id)
    {
        // Lấy kế hoạch thanh toán từ cơ sở dữ liệu
        $data['plan'] = Plan::findOrFail($id);

        // Lấy tất cả các gateway có trạng thái là 'active'
        $data['gateways'] = Gateway::where('status', 1)->latest()->get();

        // Tiêu đề trang
        $data['title'] = "Payment Methods";

        // Trả về view với dữ liệu đã chuẩn bị
        return view(Helper::theme() . "user.gateway.gateways")->with($data);
    }

    // Xử lý yêu cầu thanh toán ngay lập tức
    public function paynow(PaymentRequest $request)
    {
        // Gọi dịch vụ để xử lý thanh toán
        $isSuccess = $this->payment->payNow($request);

        // Kiểm tra nếu có lỗi trong quá trình thanh toán
        if ($isSuccess['type'] === 'error') {
            return redirect()->back()->with('error', $isSuccess['message']);
        }
        
        // Nếu thành công, chuyển hướng đến URL thanh toán
        return redirect()->to($isSuccess['message']);
    }

    // Hiển thị chi tiết gateway và xử lý yêu cầu thanh toán
    public function gatewaysDetails($id)
    {
        // Gọi dịch vụ để lấy thông tin chi tiết về gateway
        $isSuccess = $this->payment->details($id);

        // Kiểm tra nếu có lỗi khi lấy thông tin chi tiết
        if ($isSuccess['type'] === 'error') {
            return redirect()->back()->with('error', $isSuccess['message']);
        }

        // Trả về view với dữ liệu đã lấy từ dịch vụ
        return view($isSuccess['view'])->with($isSuccess['data']);
    }

    // Xử lý chuyển hướng đến gateway thanh toán (redirect)
    public function gatewayRedirect(Request $request, $id)
    {
        // Lấy thông tin gateway từ cơ sở dữ liệu
        $gateway = Gateway::where('status', 1)->findOrFail($id);

        // Kiểm tra loại giao dịch (deposit hoặc payment)
        if (session('type') == 'deposit') {
            // Nếu là deposit, lấy thông tin deposit từ session
            $deposit = Deposit::where('trx', session('trx'))->firstOrFail();
        } else {
            // Nếu là payment, lấy thông tin payment từ session
            $deposit = Payment::where('trx', session('trx'))->firstOrFail();
        }

        // Kiểm tra loại gateway, xử lý theo từng loại (manual hoặc online)
        if ($gateway->type == 0) {
            // Nếu gateway là manual, sử dụng dịch vụ Manual để xử lý
            $data = Manual::process($request, $gateway, $deposit->total, $deposit);
        } else {
            // Nếu gateway là online, lấy tên của gateway và gọi dịch vụ tương ứng
            $class = 'App\Services\Gateway\\' . ucwords($gateway->name).'Service';

            // Nếu là gateway gourl, sử dụng lớp Gourl
            if (strstr($gateway->name, 'gourl')) {
                $method = new Gourl;
            } else {
                // Tạo đối tượng của lớp tương ứng với gateway
                $method = new $class;
            }

            // Xử lý thanh toán với dịch vụ của gateway
            $data = $method::process($request, $gateway, $deposit->total, $deposit);
        }

        // Kiểm tra nếu có lỗi trong quá trình thanh toán
        if ($data['type'] === 'error') {
            return redirect()->back()->with('error', $data['message']);
        }

        // Chuyển hướng đến các URL thanh toán khác nhau tùy theo tên gateway
        if ($gateway->name == 'mercadopago') {
            return redirect()->to($data['message']);
        }

        if (strstr($gateway->name, 'gourl')) {
            return redirect()->to($data['data']);
        }

        if ($gateway->name == 'nowpayments') {
            return redirect()->to($data['data']->invoice_url);
        }

        if ($gateway->name == 'mollie') {
            return redirect()->to($data['redirect_url']);
        }

        if ($gateway->name == 'paghiper') {
            return redirect()->to($data['data']);
        }

        if ($gateway->name == 'coinpayments') {
            // Kiểm tra nếu checkout_url có trong kết quả
            if (isset($data['result']['checkout_url'])) {
                return redirect()->to($data['result']['checkout_url']);
            }
        }

        if ($gateway->name == 'paypal') {
            // Chuyển hướng đến link Paypal sau khi nhận được dữ liệu JSON
            $data = json_decode($data);
            return redirect()->to($data->links[1]->href);
        }

        // Nếu là phương thức thanh toán thủ công (manual)
        if ($gateway->name == 'paytm') {
            return view(Helper::theme() . 'user.gateway.auto', compact('data'));
        }

        // Nếu không phải thủ công, quay lại trang dashboard với thông báo thành công
        $is_manual = session('manual') != null && session('manual') == 'yes' ? 1 : 0;

        if ($is_manual) {
            return redirect()->route('user.dashboard')->with('success', 'Your Payment is Successfully Processing');
        }

        // Trở lại dashboard với thông báo đã nhận thanh toán thành công
        return redirect()->route('user.dashboard')->with('success', 'Your Payment is Successfully Recieved');
    }

    // Xử lý khi thanh toán thành công
    public function paymentSuccess(Request $request, $gateway)
    {
        // Lấy thông tin gateway từ cơ sở dữ liệu
        $gateway = Gateway::where('name', $gateway)->first();

        // Tạo đối tượng của lớp xử lý tương ứng với gateway
        $class = 'App\Services\Gateway\\' . ucwords($gateway->name).'Service';

        // Nếu là gourl, sử dụng lớp Gourl
        if (strstr($gateway->name, 'gourl')) {
            $method = new Gourl;
        } else {
            $method = new $class;
        }

        // Kiểm tra kết quả thanh toán thành công
        $isSuccess = $method::success($request);

        // Kiểm tra nếu có lỗi trong quá trình thanh toán thành công
        if ($isSuccess['type'] == 'error') {
            return redirect()->route('user.dashboard')->with('error', $isSuccess['message']);
        }

        // Quay lại dashboard với thông báo thành công
        return redirect()->route('user.dashboard')->with('success', $isSuccess['message']);
    }
}
