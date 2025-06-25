<?php

namespace App\Http\Controllers\Backend; // Đặt namespace cho controller này vào nhóm Backend

use App\Helpers\Helper\Helper; // Sử dụng Helper từ thư mục Helpers để làm việc với các chức năng tiện ích
use App\Http\Controllers\Controller; // Kế thừa controller cơ bản của Laravel
use App\Models\Configuration; // Sử dụng model Configuration để làm việc với bảng cấu hình
use App\Models\Deposit; // Sử dụng model Deposit để làm việc với bảng các giao dịch nạp tiền
use App\Models\Gateway; // Sử dụng model Gateway để làm việc với các cổng thanh toán
use App\Models\Template; // Sử dụng model Template để làm việc với bảng các mẫu email
use App\Models\Transaction; // Sử dụng model Transaction để làm việc với bảng các giao dịch
use Carbon\Carbon; // Sử dụng Carbon để làm việc với ngày tháng
use Illuminate\Http\Request; // Lớp Request của Laravel để xử lý các yêu cầu HTTP

class ManageDepositController extends Controller // Khai báo lớp ManageDepositController để xử lý các yêu cầu liên quan đến quản lý giao dịch nạp tiền
{
    // Hàm hiển thị danh sách các giao dịch nạp tiền
    public function index(Request $request)
    {
        // Kiểm tra trạng thái của giao dịch (online hoặc offline)
        $type = $request->status === 'online' ? 1 : 0;

        $deposit = Deposit::query(); // Khởi tạo truy vấn để lấy tất cả các giao dịch nạp tiền

        // Nếu có tìm kiếm, thêm điều kiện tìm kiếm vào truy vấn
        if($request->search){
            $deposit->search($request->search); // Giả sử đây là phương thức tìm kiếm tùy chỉnh
        }

        // Nếu có lọc theo ngày, thêm điều kiện thời gian vào truy vấn
        if($request->date){

            // Chuyển đổi chuỗi ngày thành mảng ngày
            $date = array_map(function($item){
                return Carbon::parse($item); // Chuyển đổi ngày thành đối tượng Carbon để dễ so sánh
            },explode(' - ', $request->date));

            // Lọc giao dịch theo khoảng thời gian
            $deposit->whereBetween('created_at', $date);
        }

        // Nếu trạng thái là online, chỉ lấy các giao dịch có trạng thái là 1 (đã xác nhận)
        if($type == 1){
            $deposit->where('status',1);
        }else{ // Nếu trạng thái là offline, lấy các giao dịch chưa xác nhận
            $deposit->where('type', $type);
        }

        // Lấy các giao dịch, kèm theo thông tin cổng thanh toán và người dùng, sắp xếp theo thời gian và phân trang
        $data['deposits'] = $deposit->with('gateway','user')->latest()->paginate( Helper::pagination());

        // Tiêu đề trang
        $data['title'] = 'Manage Deposits';

        // Trả về view quản lý giao dịch nạp tiền với dữ liệu đã chuẩn bị
        return view('backend.deposit.index')->with($data);
    }

    // Hàm hiển thị chi tiết giao dịch nạp tiền
    public function details($trx)
    {
        // Lấy thông tin giao dịch theo mã giao dịch (trx)
        $data['deposit'] = Deposit::where('trx', $trx)->firstOrFail();

        // Tiêu đề trang
        $data['title'] = 'Deposit Details';

        // Trả về view chi tiết giao dịch
        return view('backend.deposit.details')->with($data);
    }

    // Hàm xử lý chấp nhận giao dịch nạp tiền
    public function accept(Request $request)
    { 
        
        // Lấy thông tin giao dịch theo mã giao dịch (trx)
        $deposit = Deposit::where('trx', $request->trx)->firstOrFail();
        
        // Lấy cấu hình chung của ứng dụng
        $general = Configuration::first();

        // Lấy thông tin cổng thanh toán của giao dịch
        $gateway = Gateway::find($deposit->gateway_id);

        // Cập nhật trạng thái của giao dịch là đã chấp nhận (status = 1)
        $deposit->status = 1;
        $deposit->save();

        // Cập nhật số dư của người dùng sau khi nạp tiền
        $deposit->user->balance = $deposit->user->balance + $deposit->amount;
        $deposit->user->save();

        // Tạo giao dịch ghi nhận số tiền nạp vào
        Transaction::create([
            'trx' => $deposit->trx,
            'amount' => $deposit->amount,
            'details' => 'Deposit Successfull', // Chi tiết giao dịch
            'charge' => $gateway->charge, // Phí của cổng thanh toán
            'type' => '+', // Loại giao dịch là cộng vào
            'user_id' => $deposit->user_id // ID người dùng
        ]);

        // Lấy mẫu email xác nhận thanh toán đã thành công
        $template = Template::where('name','payment_confirmed')->where('status',1)->first();

        // Nếu tìm thấy mẫu email, gửi email cho người dùng
        if($template){

            $data = [
                'username' => $deposit->user->username, // Tên người dùng
                'email' => $deposit->user->email, // Email người dùng
                'app_name' => $general->appname, // Tên ứng dụng
                'trx' => $deposit->trx,  // Mã giao dịch
                'amount' => $deposit->amount, // Số tiền nạp
                'charge' => number_format($gateway->charge, 4), // Phí giao dịch
                'plan' => '', // Kế hoạch (rỗng trong trường hợp này)
                'currency' => $general->currency // Đơn vị tiền tệ
            ];

            // Gửi email xác nhận
            Helper::fireMail($data, $template);
        }

        // Trả về thông báo thành công và quay lại trang trước
        return redirect()->back()->with('success', "Payment Confirmed Successfully");
    }

    // Hàm xử lý từ chối giao dịch nạp tiền
    public function reject(Request $request)
    { 
        
        // Lấy thông tin giao dịch theo mã giao dịch (trx)
        $deposit = Deposit::where('trx', $request->trx)->firstOrFail();
        
        // Lấy cấu hình chung của ứng dụng
        $general = Configuration::first();

        // Lấy thông tin cổng thanh toán của giao dịch
        $gateway = Gateway::find($deposit->gateway_id);

        // Cập nhật trạng thái của giao dịch là đã bị từ chối (status = 3)
        $deposit->status = 3;
        $deposit->save();

        // Lấy mẫu email thông báo thanh toán bị từ chối
        $template = Template::where('name','payment_rejected')->where('status',1)->first();

        // Nếu tìm thấy mẫu email, gửi email thông báo cho người dùng
        if($template){

            $data = [
                'username' => $deposit->user->username, // Tên người dùng
                'email' => $deposit->user->email, // Email người dùng
                'app_name' => $general->appname, // Tên ứng dụng
                'trx' => $deposit->trx,  // Mã giao dịch
                'amount' => $deposit->amount, // Số tiền nạp
                'charge' => number_format($gateway->charge, 4), // Phí giao dịch
                'plan' => '', // Kế hoạch (rỗng trong trường hợp này)
                'currency' => $general->currency // Đơn vị tiền tệ
            ];

            // Gửi email thông báo giao dịch bị từ chối
            Helper::fireMail($data, $template);
        }

        // Trả về thông báo thành công và quay lại trang trước
        return back()->with('success', "Payment Rejected Successfully");
    }
}
