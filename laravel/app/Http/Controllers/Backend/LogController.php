<?php

// Khai báo namespace cho controller, đặt trong thư mục Backend của ứng dụng
namespace App\Http\Controllers\Backend;

// Nhập các lớp và helper cần thiết
use App\Helpers\Helper\Helper; // Helper cung cấp các hàm tiện ích (phân trang, cấu hình, v.v.)
use App\Http\Controllers\Controller; // Class cơ sở cho controller
use App\Models\Deposit; // Model đại diện cho bảng deposits (giao dịch nạp tiền)
use App\Models\MoneyTransfer; // Model đại diện cho bảng money_transfers (chuyển tiền)
use App\Models\Payment; // Model đại diện cho bảng payments (thanh toán)
use App\Models\Trade; // Model đại diện cho bảng trades (giao dịch trade)
use App\Models\ReferralCommission; // Model đại diện cho bảng referral_commissions (hoa hồng giới thiệu)
use App\Models\RefferedCommission; // Model đại diện cho bảng reffered_commissions (không sử dụng trong mã này)
use App\Models\Transaction; // Model đại diện cho bảng transactions (giao dịch)
use App\Models\User; // Model đại diện cho bảng users (người dùng)
use App\Models\Withdraw; // Model đại diện cho bảng withdraws (giao dịch rút tiền)
use Carbon\Carbon; // Thư viện xử lý ngày giờ
use Illuminate\Http\Request; // Class xử lý request HTTP

// Class LogController kế thừa từ Controller
class LogController extends Controller
{
    // Phương thức tradeLog: Hiển thị nhật ký giao dịch trade
    public function tradeLog(Request $request)
    {
        // Tìm người dùng theo ID (nếu có)
        $user = User::find($request->user);
        $trades = Trade::query();

        // Nếu có user, lọc giao dịch theo user_id
        if ($user) {
            $trades->where('user_id', $user->id);
        }

        // Đặt tiêu đề trang
        $data['title'] = 'Wheel Log';

        // Xử lý yêu cầu AJAX
        if ($request->ajax()) {
            // Lọc giao dịch theo bộ lọc AJAX
            $trades = $this->ajaxFilter($trades->with('user'), $request);
            // Trả về view AJAX
            return view('backend.logs.trade_ajax', compact('trades'));
        }

        // Lấy danh sách giao dịch với phân trang và sắp xếp theo ID giảm dần
        $data['trades'] = $trades->orderBy('id', 'desc')->with('user')->paginate(Helper::pagination());

        // Trả về view nhật ký giao dịch
        return view('backend.logs.trade_log')->with($data);
    }

    // Phương thức transaction: Hiển thị nhật ký giao dịch
    public function transaction(Request $request)
    {
        // Tìm người dùng theo ID (nếu có)
        $user = User::find($request->user);

        // Đặt tiêu đề trang
        $data['title'] = 'Transaction Log';

        $transactions = Transaction::query();

        // Nếu có user, lọc giao dịch theo user_id
        if ($user) {
            $transactions->where('user_id', $user->id);
        }

        // Nếu có tìm kiếm, lọc giao dịch theo mã giao dịch (trx)
        if ($request->search) {
            $transactions->where('trx', 'LIKE', '%' . $request->search . '%');
        }

        // Xử lý yêu cầu AJAX
        if ($request->ajax()) {
            // Lọc giao dịch theo bộ lọc AJAX
            $transactions = $this->ajaxFilter($transactions->with('user'), $request);
            // Trả về view AJAX
            return view('backend.logs.transaction_ajax', compact('transactions'));
        }

        // Lấy danh sách giao dịch với phân trang và sắp xếp theo mới nhất
        $data['transactions'] = $transactions->latest()->with('user')->paginate(Helper::pagination());

        // Trả về view nhật ký giao dịch
        return view('backend.logs.transaction')->with($data);
    }

    // Phương thức Commision: Hiển thị nhật ký hoa hồng giới thiệu
    public function Commision(Request $request, $user = '')
    {
        // Tìm người dùng theo ID (nếu có)
        $user = User::find($user);

        $commisons = ReferralCommission::query();

        // Nếu có user, lọc hoa hồng theo người nhận
        if ($user) {
            $commisons->where('commission_to', $user->id);
        }

        // Xử lý yêu cầu AJAX
        if ($request->ajax()) {
            // Lọc hoa hồng theo bộ lọc AJAX
            $commisons = $this->ajaxFilter($commisons->with('whoGetTheMoney', 'whoSendTheMoney'), $request);
            // Trả về view AJAX
            return view('backend.logs.commission_ajax', compact('commisons'));
        }

        // Lấy danh sách hoa hồng với phân trang và sắp xếp theo mới nhất
        $commisons = $commisons->latest()->with('whoGetTheMoney', 'whoSendTheMoney')->paginate(Helper::pagination());

        // Đặt tiêu đề trang
        $title = 'Commission Log';

        // Trả về view nhật ký hoa hồng
        return view('backend.logs.commission', compact('commisons', 'title'));
    }

    // Phương thức depositLog: Hiển thị nhật ký nạp tiền
    public function depositLog(Request $request, $user = '')
    {
        // Tìm người dùng theo ID (nếu có)
        $user = User::find($user);

        // Đặt tiêu đề trang
        $data['title'] = "Deposit Log";

        $depo = Deposit::query();

        // Nếu có user, lọc nạp tiền theo user_id
        if ($user) {
            $depo->where('user_id', $user->id);
        }

        // Xử lý yêu cầu AJAX
        if ($request->ajax()) {
            // Lọc nạp tiền theo bộ lọc AJAX, chỉ lấy các trạng thái 1, 2, 3
            $deposits = $this->ajaxFilter($depo->whereIn('status', [1, 2, 3])->with('user', 'gateway'), $request);
            // Trả về view AJAX
            return view('backend.logs.deposit_ajax', compact('deposits'));
        }

        // Lấy danh sách nạp tiền với phân trang và sắp xếp theo mới nhất
        $data['deposits'] = $depo->whereIn('status', [1, 2, 3])->with('user', 'gateway')->latest()->paginate(Helper::pagination());

        // Trả về view nhật ký nạp tiền
        return view('backend.logs.deposit_log')->with($data);
    }

    // Phương thức depositDetails: Hiển thị chi tiết giao dịch nạp tiền
    public function depositDetails(Request $request)
    {
        // Đặt tiêu đề trang
        $title = "Payment Details";

        // Tìm giao dịch nạp tiền theo transaction_id
        $manual = Deposit::where('transaction_id', $request->trx)->firstOrFail();

        // Trả về view chi tiết giao dịch
        return view('backend.deposit_details', compact('title', 'manual'));
    }

    // Phương thức paymentReport: Hiển thị báo cáo thanh toán
    public function paymentReport(Request $request, $user = '')
    {
        // Tìm người dùng theo ID (nếu có)
        $user = User::find($user);

        // Đặt tiêu đề trang
        $data['title'] = 'Payment Report';

        // Lấy danh sách thanh toán với trạng thái đã xác nhận (status = 1)
        $transactions = Payment::where('status', 1);

        // Nếu có user, lọc thanh toán theo user_id
        if ($user) {
            $transactions->where('user_id', $user->id);
        }

        // Nếu có tìm kiếm, lọc thanh toán theo mã giao dịch (trx)
        if ($request->search) {
            $transactions->where('trx', 'LIKE', '%' . $request->search . '%');
        }

        // Xử lý yêu cầu AJAX
        if ($request->ajax()) {
            // Lọc thanh toán theo bộ lọc AJAX
            $transactions = $this->ajaxFilter($transactions->with('user', 'gateway'), $request);
            // Trả về view AJAX
            return view('backend.logs.payment_ajax', compact('transactions'));
        }

        // Lấy danh sách thanh toán với phân trang và sắp xếp theo mới nhất
        $data['transactions'] = $transactions->latest()->with('gateway', 'user')->paginate(Helper::pagination());

        // Trả về view báo cáo thanh toán
        return view('backend.logs.payment_report')->with($data);
    }

    // Phương thức withdarawReport: Hiển thị báo cáo rút tiền
    public function withdarawReport(Request $request, $user = '')
    {
        // Tìm người dùng theo ID (nếu có)
        $user = User::find($user);

        // Đặt tiêu đề trang
        $data['title'] = 'Withdraw Report';

        // Lấy danh sách rút tiền với trạng thái đã xác nhận (status = 1)
        $data['transactions'] = Withdraw::where('status', 1);

        // Nếu có user, lọc rút tiền theo user_id
        if ($user) {
            $data['transactions']->where('user_id', $user->id);
        }

        // Nếu có tìm kiếm, lọc rút tiền theo transaction_id
        if ($request->search) {
            $data['transactions']->where('transaction_id', 'LIKE', '%' . $request->search . '%');
        }

        // Xử lý yêu cầu AJAX
        if ($request->ajax()) {
            // Lọc rút tiền theo bộ lọc AJAX
            $data['transactions'] = $this->ajaxFilter($data['transactions']->with('user', 'withdrawMethod'), $request);
            // Trả về view AJAX
            return view('backend.logs.withdraw_ajax')->with($data);
        }

        // Lấy danh sách rút tiền với phân trang và sắp xếp theo mới nhất
        $data['transactions'] = $data['transactions']->latest()->with('user', 'withdrawMethod')->paginate(Helper::pagination());

        // Trả về view báo cáo rút tiền
        return view('backend.logs.withdraw_report')->with($data);
    }

    // Phương thức transferLog: Hiển thị nhật ký chuyển tiền
    public function transferLog(Request $request)
    {
        // Đặt tiêu đề trang
        $data['title'] = 'Transfer Log';

        $transfers = MoneyTransfer::query();

        // Nếu có tìm kiếm, lọc chuyển tiền theo transaction_id
        if ($request->search) {
            $transfers->where('transaction_id', 'LIKE', '%' . $request->search . '%');
        }

        // Xử lý yêu cầu AJAX
        if ($request->ajax()) {
            // Lọc chuyển tiền theo bộ lọc AJAX
            $transfers = $this->ajaxFilter($transfers->with('sender', 'receiver'), $request);
            // Trả về view AJAX
            return view('backend.logs.transfer_ajax', compact('transfers'));
        }

        // Lấy danh sách chuyển tiền với phân trang và sắp xếp theo mới nhất
        $data['transfers'] = MoneyTransfer::latest()->with('sender', 'receiver')->paginate(Helper::pagination());

        // Trả về view nhật ký chuyển tiền
        return view('backend.logs.transfer_report')->with($data);
    }

    // Phương thức ajaxFilter: Lọc dữ liệu theo thời gian cho yêu cầu AJAX
    public function ajaxFilter($transactions, $request)
    {
        return $transactions->when($request->key, function ($query) use ($request) {
            // Lọc theo các khoảng thời gian được chọn
            if ($request->key === 'Today') {
                $query->whereDate('created_at', now());
            } elseif ($request->key === 'Yesterday') {
                $query->whereDate('created_at', now()->subDay());
            } elseif ($request->key === 'Last 7 Days') {
                $query->whereDate('created_at', '>=', now()->subDays(7));
            } elseif ($request->key === 'Last 30 Days') {
                $query->whereDate('created_at', '>=', now()->subDays(30));
            } elseif ($request->key === 'This Month') {
                $query->whereMonth('created_at', now());
            } else {
                // Lọc theo khoảng thời gian tùy chỉnh (startdate - enddate)
                [$startdate, $enddate] = array_map(function ($item) {
                    return Carbon::parse($item);
                }, explode('-', $request->date));
                $query->whereBetween('created_at', [$startdate, $enddate]);
            }
        })->latest()->get();
    }
}