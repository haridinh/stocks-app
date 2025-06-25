<?php

namespace App\Http\Controllers\backend; // Đặt namespace cho controller này vào nhóm Backend

use App\Helpers\Helper\Helper; // Sử dụng Helper từ thư mục Helpers để hỗ trợ các chức năng tiện ích
use App\Http\Controllers\Controller; // Kế thừa controller cơ bản của Laravel
use App\Models\Admin; // Mô hình quản lý quản trị viên (Admin)
use App\Models\Configuration; // Mô hình cấu hình chung
use App\Models\Gateway; // Mô hình Gateway thanh toán
use App\Models\GeneralSetting; // Mô hình cấu hình chung của hệ thống
use App\Models\Payment; // Mô hình quản lý thanh toán
use App\Models\PlanSubscription; // Mô hình đăng ký kế hoạch
use App\Models\Template; // Mô hình quản lý mẫu thông báo
use App\Models\Transaction; // Mô hình giao dịch
use App\Notifications\PlanSubscriptionNotification; // Thông báo đăng ký kế hoạch
use Carbon\Carbon; // Thư viện Carbon để làm việc với ngày tháng
use Illuminate\Http\Request; // Lớp Request của Laravel để xử lý các yêu cầu HTTP
use Illuminate\Support\Facades\DB; // Sử dụng DB để thực hiện các truy vấn cơ sở dữ liệu

class PaymentController extends Controller // Controller xử lý các thao tác liên quan đến thanh toán
{
    // Hiển thị danh sách thanh toán và lọc theo các tham số (loại thanh toán, ngày, tìm kiếm)
    public function payment(Request $request)
    {
        $dates = [];
        
        // Nếu có tham số ngày, chuyển nó thành một mảng các đối tượng Carbon
        if($request->dates){
            $dates = array_map(function($q){
                return Carbon::parse($q);
            }, explode('-', $request->dates));
        }

        // Xác định loại thanh toán (online hoặc offline)
        $type = $request->type === 'online' ? 1 : 0;

        // Truy vấn thanh toán
        $payment = Payment::query();

        // Nếu là thanh toán online, sử dụng scope onlinePayment() đã định nghĩa trong mô hình Payment
        if ($type) {
            $payment->onlinePayment();
        } else {
            // Nếu là thanh toán offline, sử dụng scope offlinePayment() đã định nghĩa trong mô hình Payment
            $payment->offlinePayment();
        }

        $data['title'] = 'Manage payments'; // Tiêu đề trang

        // Lọc thanh toán theo ngày và tìm kiếm (nếu có)
        $data['payments'] = $payment->when($request->dates, function($q) use ($dates){
            $q->whereBetween('created_at', $dates); // Lọc theo khoảng thời gian
        })->search($request->search) // Lọc theo tìm kiếm
          ->latest() // Sắp xếp theo ngày tạo mới nhất
          ->with('plan', 'gateway', 'user') // Eager loading: Tải kế hoạch, cổng thanh toán và người dùng liên quan
          ->paginate(Helper::pagination()); // Phân trang kết quả

        // Trả về view với dữ liệu thanh toán
        return view('backend.payments.index')->with($data);
    }

    // Hiển thị chi tiết thanh toán theo ID
    public function details($id)
    {
        $data['payment'] = Payment::findOrFail($id); // Lấy thanh toán theo ID, nếu không tìm thấy trả về lỗi 404

        $data['title'] = 'Payment Details'; // Tiêu đề trang

        // Trả về view chi tiết thanh toán
        return view('backend.payments.details')->with($data);
    }

    // Xác nhận thanh toán (chấp nhận thanh toán)
    public function accept(Request $request)
    {
        // Lấy thanh toán theo mã giao dịch (trx)
        $payment = Payment::where('trx', $request->trx)->firstOrFail();

        // Lấy cấu hình chung
        $general = Configuration::first();

        // Lấy cổng thanh toán (gateway)
        $gateway = $payment->gateway;

        // Cập nhật trạng thái thanh toán thành "đã xác nhận"
        $payment->status = 1;
        $payment->save();

        // Chuyển tiền hoa hồng cho người giới thiệu (nếu có)
        Helper::referMoney($payment->user_id, $payment->user->refferedBy, 'invest', $payment->amount);

        // Lấy quản trị viên có loại "super" (quản trị viên cấp cao nhất)
        $admin = Admin::where('type', 'super')->first();

        // Dữ liệu gửi cho thông báo đăng ký kế hoạch
        $data = [
            'plan_id' => $payment->plan_id,
            'user_id' => $payment->user_id,
            'expired' => $payment->plan_expired_at
        ];

        // Đăng ký kế hoạch cho người dùng
        $subscription = $this->subscription($data, $payment->user);

        // Gửi thông báo đăng ký kế hoạch cho admin
        $admin->notify(new PlanSubscriptionNotification($subscription));

        // Tạo giao dịch thành công
        Transaction::create([
            'trx' => $payment->trx,
            'amount' => $payment->amount,
            'details' => 'Payment Successfull', // Chi tiết giao dịch
            'charge' => $gateway->charge, // Phí cổng thanh toán
            'type' => '-', // Loại giao dịch
            'user_id' => $payment->user_id // ID người dùng
        ]);

        // Lấy mẫu thông báo "payment_confirmed"
        $templete = Template::where('name', 'payment_confirmed')->where('status',1)->first();

        // Nếu tìm thấy mẫu thông báo
        if($templete){
            // Gửi email thông báo cho người dùng về thanh toán thành công
            Helper::fireMail([
                'username'=>$payment->user->username,
                'email' => $payment->user->email,
                'app_name' => $general->app_name,
                'trx' => $payment->trx, 
                'amount' => $payment->amount, 
                'charge' => number_format($gateway->charge, 4), 
                'plan' => $payment->plan->name, 
                'currency' => $general->currency
            ], $templete);
        }

        // Quay lại với thông báo thành công
        return redirect()->back()->with('success', "Payment Confirmed Successfully");
    }

    // Từ chối thanh toán (cập nhật lý do từ chối)
    public function reject(Request $request)
    {
        // Lấy thanh toán theo mã giao dịch (trx)
        $payment = Payment::where('trx', $request->trx)->firstOrFail();

        // Lấy cấu hình chung
        $general = Configuration::first();

        // Lấy cổng thanh toán
        $gateway = $payment->gateway;

        // Cập nhật lý do từ chối và trạng thái thanh toán thành "bị từ chối"
        $payment->rejection_reason = $request->reason;
        $payment->status = 3;

        $payment->save();

        // Lấy mẫu thông báo "payment_rejected"
        $templete = Template::where('name', 'payment_rejected')->where('status',1)->first();

        // Nếu tìm thấy mẫu thông báo
        if($templete){
            // Gửi email thông báo cho người dùng về thanh toán bị từ chối
            Helper::fireMail([
                'username'=>$payment->user->username,
                'email' => $payment->user->email,
                'app_name' => $general->app_name,
                'trx' => $payment->trx, 
                'amount' => $payment->amount, 
                'charge' => number_format($gateway->charge, 4), 
                'plan' => $payment->plan->name, 
                'currency' => $general->currency
            ], $templete);
        }

        // Quay lại với thông báo thành công
        return back()->with('success', "Payment Rejected Successfully");
    }

    // Hàm xử lý đăng ký kế hoạch cho người dùng
    private function subscription($data, $user)
    {
        $subscription = $user->subscriptions;

        // Nếu người dùng đã có đăng ký kế hoạch, cập nhật trạng thái "is_current" của các đăng ký trước thành 0
        if ($subscription) {
            DB::table('plan_subscriptions')->where('user_id', $user->id)->update(['is_current' => 0]);
        }

        // Tạo đăng ký kế hoạch mới cho người dùng
        $id = PlanSubscription::create([
            'plan_id' => $data['plan_id'],
            'user_id' => $data['user_id'],
            'is_current' => 1, // Đánh dấu là kế hoạch hiện tại
            'plan_expired_at' => $data['expired'] // Thời gian hết hạn
        ]);

        // Trả về ID của đăng ký kế hoạch vừa tạo
        return $id;
    }
}
