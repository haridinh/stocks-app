<?php

namespace App\Http\Controllers; // Định nghĩa namespace của controller, giúp phân loại mã nguồn

// Import các lớp cần thiết
use App\Helpers\Helper\Helper; // Lớp Helper hỗ trợ các chức năng chung, như lấy theme, phân trang, v.v.
use App\Models\Deposit; // Lớp Deposit để làm việc với bảng các giao dịch gửi tiền
use App\Models\Payment; // Lớp Payment để làm việc với bảng các giao dịch thanh toán
use App\Models\PlanSubscription; // Lớp PlanSubscription để làm việc với bảng các đăng ký kế hoạch
use App\Models\ReferralCommission; // Lớp ReferralCommission để làm việc với bảng hoa hồng giới thiệu
use App\Models\Transaction; // Lớp Transaction để làm việc với bảng các giao dịch chung
use App\Models\Withdraw; // Lớp Withdraw để làm việc với bảng các giao dịch rút tiền
use Illuminate\Http\Request; // Lớp Request để lấy và xử lý các yêu cầu HTTP

class LogController extends Controller // Controller này xử lý các yêu cầu liên quan đến việc xem nhật ký giao dịch của người dùng
{
    // Phương thức depositLog: Xử lý yêu cầu xem nhật ký gửi tiền của người dùng
    public function depositLog(Request $request)
    {
        $data['title'] = "Deposit Log"; // Tiêu đề trang

        // Lọc các giao dịch gửi tiền theo mã giao dịch (trx) và ngày gửi nếu có
        $data['deposits'] = Deposit::when($request->trx, function ($item) use ($request) {
            $item->where('trx', $request->trx); // Lọc theo mã giao dịch
        })->when($request->date, function ($item) use ($request) {
            $item->whereDate('created_at', $request->date); // Lọc theo ngày
        })
            ->where('user_id', auth()->id()) // Lọc theo người dùng đã đăng nhập
            ->latest()->with('user') // Sắp xếp theo thứ tự mới nhất và lấy thông tin người dùng
            ->whereIn('status', [1, 2, 3]) // Chỉ lấy các giao dịch có trạng thái hợp lệ
            ->latest() // Sắp xếp lại theo thứ tự mới nhất
            ->with('gateway') // Lấy thông tin cổng thanh toán (gateway)
            ->paginate(Helper::pagination()); // Phân trang kết quả

        return view(Helper::theme() . 'user.deposit_log')->with($data); // Trả về view nhật ký gửi tiền
    }

    // Phương thức allWithdraw: Xử lý yêu cầu xem tất cả các giao dịch rút tiền của người dùng
    public function allWithdraw(Request $request)
    {
        $data['title'] = 'All withdraw'; // Tiêu đề trang

        // Lọc các giao dịch rút tiền theo mã giao dịch (trx) và ngày rút nếu có
        $data['withdrawlogs'] = Withdraw::when($request->trx, function ($item) use ($request) {
            $item->where('trx', $request->trx); // Lọc theo mã giao dịch
        })->when($request->date, function ($item) use ($request) {
            $item->whereDate('created_at', $request->date); // Lọc theo ngày
        })->where('user_id', auth()->id()) // Lọc theo người dùng đã đăng nhập
        ->latest()->with('withdrawMethod') // Sắp xếp theo thứ tự mới nhất và lấy phương thức rút tiền
        ->paginate(Helper::pagination()); // Phân trang kết quả

        return view(Helper::theme() . 'user.withdraw.withdraw_log')->with($data); // Trả về view nhật ký rút tiền
    }

    // Phương thức pendingWithdraw: Xử lý yêu cầu xem các giao dịch rút tiền đang chờ xử lý
    public function pendingWithdraw()
    {
        $data['title'] = 'Pending withdraw'; // Tiêu đề trang

        // Lọc các giao dịch rút tiền có trạng thái "đang chờ" (status = 0)
        $data['withdrawlogs'] = Withdraw::where('user_id', auth()->id()) // Lọc theo người dùng
            ->where('status', 0) // Trạng thái chờ xử lý
            ->latest()->with('withdrawMethod') // Sắp xếp theo thứ tự mới nhất và lấy phương thức rút tiền
            ->paginate(Helper::pagination()); // Phân trang kết quả

        return view(Helper::theme() . 'user.withdraw.withdraw_log')->with($data); // Trả về view nhật ký rút tiền
    }

    // Phương thức completeWithdraw: Xử lý yêu cầu xem các giao dịch rút tiền đã hoàn tất
    public function completeWithdraw()
    {
        $data['title'] = 'Complete withdraw'; // Tiêu đề trang

        // Lọc các giao dịch rút tiền có trạng thái "hoàn tất" (status = 1)
        $data['withdrawlogs'] = Withdraw::where('user_id', auth()->id()) // Lọc theo người dùng
            ->where('status', 1) // Trạng thái đã hoàn tất
            ->latest()->with('withdrawMethod') // Sắp xếp theo thứ tự mới nhất và lấy phương thức rút tiền
            ->paginate(10); // Phân trang với 10 mục mỗi trang

        return view(Helper::theme() . 'user.withdraw.withdraw_log')->with($data); // Trả về view nhật ký rút tiền
    }

    // Phương thức investLog: Xử lý yêu cầu xem nhật ký đầu tư của người dùng
    public function investLog(Request $request)
    {
        $data['title'] = 'Invest Log'; // Tiêu đề trang

        // Lọc các giao dịch đầu tư theo mã giao dịch (trx) và ngày đầu tư nếu có
        $data['investments'] = Payment::when($request->trx, function ($item) use ($request) {
            $item->where('trx', $request->trx); // Lọc theo mã giao dịch
        })->when($request->date, function ($item) use ($request) {
            $item->whereDate('created_at', $request->date); // Lọc theo ngày
        })->where('user_id', auth()->id()) // Lọc theo người dùng đã đăng nhập
        ->whereIn('status', [1, 2, 3]) // Lọc theo các trạng thái hợp lệ
        ->latest()->with('user', 'gateway') // Sắp xếp theo thứ tự mới nhất và lấy thông tin người dùng và cổng thanh toán
        ->paginate(Helper::pagination()); // Phân trang kết quả

        return view(Helper::theme() . 'user.invest_log')->with($data); // Trả về view nhật ký đầu tư
    }

    // Phương thức transactionLog: Xử lý yêu cầu xem nhật ký giao dịch chung của người dùng
    public function transactionLog(Request $request)
    {
        $data['title'] = 'Transaction Log'; // Tiêu đề trang

        // Lọc các giao dịch theo mã giao dịch (trx) và ngày giao dịch nếu có
        $data['transactions'] = Transaction::with('user')->when($request->trx, function ($item) use ($request) {
            $item->where('trx', $request->trx); // Lọc theo mã giao dịch
        })->when($request->date, function ($item) use ($request) {
            $item->whereDate('created_at', $request->date); // Lọc theo ngày
        })->where('user_id', auth()->id()) // Lọc theo người dùng đã đăng nhập
        ->latest()->with('user') // Sắp xếp theo thứ tự mới nhất và lấy thông tin người dùng
        ->paginate(Helper::pagination()); // Phân trang kết quả

        return view(Helper::theme() . 'user.transaction')->with($data); // Trả về view nhật ký giao dịch
    }

    // Phương thức commision: Xử lý yêu cầu xem lịch sử hoa hồng giới thiệu của người dùng
    public function commision(Request $request)
    {
        $data['title'] = 'Refferal Commission'; // Tiêu đề trang

        // Lọc các hoa hồng giới thiệu theo ngày nếu có
        $data['commison'] = ReferralCommission::when($request->date, function ($item) use ($request) {
            $item->whereDate('created_at', $request->date); // Lọc theo ngày
        })->where('commission_to', auth()->id()) // Lọc theo người nhận hoa hồng (người dùng hiện tại)
        ->latest()->with('whoGetTheMoney', 'whoSendTheMoney') // Lấy thông tin người nhận và người gửi hoa hồng
        ->paginate(Helper::pagination()); // Phân trang kết quả

        return view(Helper::theme() . 'user.commision_log')->with($data); // Trả về view nhật ký hoa hồng
    }

    // Phương thức subscriptionLog: Xử lý yêu cầu xem nhật ký đăng ký kế hoạch của người dùng
    public function subscriptionLog(Request $request)
    {
        $data['title'] = 'Subscription Log'; // Tiêu đề trang

        // Lọc các đăng ký kế hoạch theo ngày nếu có
        $data['subscriptions'] = PlanSubscription::when($request->date, function ($item) use ($request) {
            $item->whereDate('plan_expired_at', $request->date); // Lọc theo ngày hết hạn
        })->where('user_id', auth()->id()) // Lọc theo người dùng đã đăng nhập
        ->latest()->with('user', 'plan') // Sắp xếp theo thứ tự mới nhất và lấy thông tin người dùng và kế hoạch
        ->paginate(Helper::pagination()); // Phân trang kết quả

        return view(Helper::theme() . 'user.subscription_log')->with($data); // Trả về view nhật ký đăng ký
    }

    // Phương thức refferalLog: Xử lý yêu cầu xem các người dùng giới thiệu của người dùng
    public function refferalLog()
    {
        // Lấy danh sách người giới thiệu của người dùng
        $data['reference'] = auth()->user()->refferals;

        $data['title'] = 'Refferal Log'; // Tiêu đề trang

        return view(Helper::theme() . 'user.refferal')->with($data); // Trả về view nhật ký giới thiệu
    }
}
