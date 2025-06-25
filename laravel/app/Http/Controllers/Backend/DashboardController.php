<?php

// Khai báo namespace cho controller, đặt trong thư mục Backend của ứng dụng
namespace App\Http\Controllers\Backend;

// Nhập các class và helper cần thiết
use App\Helpers\Helper\Helper; // Helper cung cấp các hàm tiện ích (phân trang, cấu hình, v.v.)
use App\Http\Controllers\Controller; // Class cơ sở cho controller
use App\Models\Admin; // Model đại diện cho bảng admins
use App\Models\Deposit; // Model đại diện cho bảng deposits (giao dịch nạp tiền)
use App\Models\Gateway; // Model đại diện cho bảng gateways (cổng thanh toán)
use App\Models\Payment; // Model đại diện cho bảng payments (thanh toán)
use App\Models\Ticket; // Model đại diện cho bảng tickets (hỗ trợ khách hàng)
use App\Models\User; // Model đại diện cho bảng users (người dùng)
use App\Models\UserLog; // Model đại diện cho bảng user_logs (nhật ký người dùng)
use App\Models\Withdraw; // Model đại diện cho bảng withdraws (giao dịch rút tiền)
use App\Models\WithdrawGateway; // Model đại diện cho bảng withdraw_gateways (cổng rút tiền)
use Illuminate\Http\Request; // Class xử lý request HTTP
use Carbon\Carbon; // Thư viện xử lý ngày giờ
use DB; // Facade để thực hiện truy vấn cơ sở dữ liệu

// Class DashboardController kế thừa từ Controller
class DashboardController extends Controller
{
    // Phương thức dashboard: Hiển thị trang tổng quan admin
    public function dashboard(Request $request)
    {
        // Đặt tiêu đề trang
        $data['title'] = __('Admin Dashboard');

        // Tổng hợp thống kê giao dịch nạp tiền
        $data['totalDeposit'] = Deposit::where('status', 1)->sum('amount'); // Tổng số tiền nạp đã xác nhận
        $data['pendingDeposit'] = Deposit::where('status', 2)->sum('amount'); // Tổng số tiền nạp đang chờ xử lý

        // Tổng hợp thống kê giao dịch rút tiền
        $data['totalWithdraw'] = Withdraw::where('status', 1)->sum('withdraw_amount'); // Tổng số tiền rút đã xác nhận
        $data['pendingWithdraw'] = Withdraw::where('status', 0)->sum('withdraw_amount'); // Tổng số tiền rút đang chờ xử lý

        // Thống kê người dùng
        $data['totalUser'] = User::count(); // Tổng số người dùng
        $data['pendingUser'] = User::where('status', 0)->count(); // Số người dùng đang chờ kích hoạt
        $data['activeUser'] = User::where('status', 1)->count(); // Số người dùng đã kích hoạt
        $data['emailUser'] = User::where('status', 1)->where('is_email_verified', 1)->count(); // Số người dùng đã xác minh email

        // Thống kê ticket hỗ trợ
        $data['totalTicket'] = Ticket::count(); // Tổng số ticket
        $data['pendingTicket'] = Ticket::whereStatus(2)->count(); // Số ticket đang chờ xử lý

        // Thống kê cổng thanh toán và rút tiền
        $data['totalOnlineGateway'] = Gateway::where('type', 1)->count(); // Số cổng thanh toán trực tuyến
        $data['totalOfflineGateway'] = Gateway::where('type', 0)->count(); // Số cổng thanh toán ngoại tuyến
        $data['totalWithdrawGateway'] = WithdrawGateway::where('status', 1)->count(); // Số cổng rút tiền đang hoạt động

        // Thống kê nhân viên (admin không phải super admin)
        $data['totalStaff'] = Admin::where('type', '!=', 'super')->count();

        // Lấy danh sách người dùng với tìm kiếm và phân trang
        $data['users'] = User::search($request->search)->latest()->paginate(Helper::pagination(), ['*'], 'users');

        // Thống kê thanh toán gói dịch vụ
        $data['subscriptionAmount'] = Payment::where('status', 1)->sum('amount'); // Tổng số tiền thanh toán gói dịch vụ
        $data['charge'] = Payment::where('status', 1)->sum('charge'); // Tổng phí thanh toán
        $data['pending_payment'] = Payment::where('status', 2)->sum('amount'); // Tổng số tiền thanh toán đang chờ xử lý
        $data['plan_expired_user'] = Payment::where('status', 1)->pluck('user_id')->unique()->count(); // Số người dùng có gói dịch vụ hết hạn

        // Khởi tạo mảng để lưu dữ liệu biểu đồ theo tháng
        $months = array();
        $totalAmount = array();
        $withdrawTotalAmount = array();
        $depositsTotalAmount = array();

        // Lấy dữ liệu thanh toán theo tháng
        $payments = Payment::where('status', 1)
            ->select(DB::raw('SUM(total) as total'), DB::raw('MONTHNAME(created_at) month'))
            ->groupBy('month')
            ->get();

        // Lấy dữ liệu rút tiền theo tháng
        $withdraws = Withdraw::where('status', 1)
            ->select(DB::raw('SUM(withdraw_amount) as total'), DB::raw('MONTHNAME(created_at) month'))
            ->groupBy('month')
            ->get();

        // Lấy dữ liệu nạp tiền theo tháng
        $deposits = Deposit::where('status', 1)
            ->select(DB::raw('SUM(total) as total'), DB::raw('MONTHNAME(created_at) month'))
            ->groupBy('month')
            ->get();

        // Tạo danh sách 12 tháng gần nhất
        for ($i = 11; $i >= 0; $i--) {
            $month = Carbon::today()->startOfMonth()->subMonth($i); // Lấy tháng cách hiện tại $i tháng
            array_push($months, $month->monthName); // Thêm tên tháng vào mảng
            array_push($totalAmount, 0); // Khởi tạo tổng thanh toán
            array_push($withdrawTotalAmount, 0); // Khởi tạo tổng rút tiền
            array_push($depositsTotalAmount, 0); // Khởi tạo tổng nạp tiền
        }

        // Cập nhật tổng thanh toán theo tháng
        foreach ($payments as $payment) {
            if (in_array($payment->month, $months)) {
                $index = array_search($payment->month, $months);
                $totalAmount[$index] = $payment->total; // Gán giá trị tổng thanh toán
            }
        }

        // Cập nhật tổng rút tiền theo tháng
        foreach ($withdraws as $withdraw) {
            if (in_array($withdraw->month, $months)) {
                $index = array_search($withdraw->month, $months);
                $withdrawTotalAmount[$index] = $withdraw->total; // Gán giá trị tổng rút tiền
            }
        }

        // Cập nhật tổng nạp tiền theo tháng
        foreach ($deposits as $deposit) {
            if (in_array($deposit->month, $months)) {
                $index = array_search($deposit->month, $months);
                $depositsTotalAmount[$index] = $deposit->total; // Gán giá trị tổng nạp tiền
            }
        }

        // Gán dữ liệu biểu đồ vào biến $data
        $data['months'] = $months;
        $data['totalAmount'] = $totalAmount;
        $data['withdrawTotalAmount'] = $withdrawTotalAmount;
        $data['depositsTotalAmount'] = $depositsTotalAmount;

        // Thống kê trình duyệt người dùng
        $data['browser'] = UserLog::select(DB::raw('COUNT(browser) as total'), 'browser')->groupBy('browser')->get();

        // Tổng số log người dùng
        $data['logTotal'] = UserLog::count();

        // Lấy 4 giao dịch đầu tư gần nhất (thanh toán gói dịch vụ)
        $data['investments'] = Payment::when($request->trx, function ($item) use ($request) {
            $item->where('transaction_id', $request->trx); // Lọc theo ID giao dịch nếu có
        })->where('status', 1)->latest()->with('user', 'plan')->limit(4)->get();

        // Lấy 4 giao dịch nạp tiền gần nhất
        $data['deposits'] = Deposit::where('status', 1)->latest()->with('user')->limit(4)->get();

        // Lấy 4 giao dịch rút tiền gần nhất
        $data['withdraws'] = Withdraw::where('status', 1)->latest()->with('user', 'withdrawMethod')->limit(4)->get();

        // Trả về view dashboard với dữ liệu
        return view('backend.dashboard')->with($data);
    }
}