<?php

namespace App\Http\Controllers\Backend; // Đặt namespace cho controller này vào nhóm Backend

use App\Helpers\Helper\Helper; // Sử dụng Helper từ thư mục Helpers để làm việc với các chức năng tiện ích
use App\Http\Controllers\Controller; // Kế thừa controller cơ bản của Laravel
use App\Http\Requests\AdminUserRequest; // Sử dụng request để xác thực các dữ liệu đầu vào khi cập nhật thông tin người dùng
use App\Jobs\SendEmailJob; // Job gửi email vào hàng đợi
use App\Jobs\SendQueueEmail; // Job gửi email trong hàng đợi (không sử dụng trong mã này, có thể là một phần không hoàn thiện)
use App\Models\GeneralSetting; // Mô hình liên quan đến các cài đặt chung của hệ thống
use App\Models\Payment; // Mô hình quản lý các giao dịch thanh toán của người dùng
use App\Models\ReferralCommission; // Mô hình quản lý hoa hồng giới thiệu
use App\Models\Template; // Mô hình quản lý các mẫu email hoặc template hệ thống
use App\Models\Transaction; // Mô hình quản lý giao dịch của người dùng
use App\Models\User; // Mô hình quản lý người dùng trong hệ thống
use App\Models\Withdraw; // Mô hình quản lý yêu cầu rút tiền của người dùng
use App\Services\AdminUserService; // Dịch vụ xử lý các thao tác liên quan đến người dùng trong backend
use Illuminate\Http\Request; // Lớp Request của Laravel để xử lý các yêu cầu HTTP
use Auth; // Laravel Auth để xử lý đăng nhập người dùng
use DB; // Lớp DB để thực hiện các truy vấn cơ sở dữ liệu

class ManageUserController extends Controller // Controller xử lý các thao tác liên quan đến người dùng trong backend
{

    protected $userservice; // Thuộc tính lưu dịch vụ quản lý người dùng

    public function __construct(AdminUserService $userservice) // Khởi tạo controller và inject dịch vụ AdminUserService
    {
        $this->userservice = $userservice;
    }

    // Hiển thị danh sách tất cả người dùng
    public function index(Request $request)
    {
        $data['title'] = 'All Users'; // Tiêu đề trang

        // Tạo truy vấn cơ bản cho bảng người dùng
        $user = User::query();

        // Nếu có từ khóa tìm kiếm, lọc người dùng theo username, email hoặc phone
        if ($request->search) {
            $user->where(function ($item) use ($request) {
                $item->where('username', $request->search)
                    ->orWhere('email', $request->email)
                    ->orWhere('phone', $request->search);
            });
        }

        // Nếu có lọc theo trạng thái người dùng, kiểm tra trạng thái (1: active, 0: inactive)
        if ($request->user_status) {
            $status = $request->user_status === 'user_active' ? 1 : 0;
            $user->where('status', $status);
        }

        // Lấy danh sách người dùng và phân trang theo số lượng cho phép
        $data['users'] = $user->latest()->paginate(Helper::pagination());

        // Trả về view danh sách người dùng
        return view('backend.users.index')->with($data);
    }

    // Hiển thị chi tiết người dùng
    public function userDetails(Request $request)
    {
        // Lấy thông tin chi tiết người dùng cùng với các thông tin phụ như referrals, transactions, payments, etc.
        $data['user'] = User::with('refferals')->where('id', $request->user)->firstOrFail();

        // Lấy thông tin thanh toán gần nhất của người dùng
        $data['payment'] = Payment::where('user_id', $data['user']->id)->where('status', 1)->latest()->first();

        // Tổng số referrals của người dùng
        $data['totalRef'] = $data['user']->refferals->count();

        // Tổng hoa hồng của người dùng
        $data['userCommission'] = $data['user']->commissions->sum('amount');

        // Tổng số tiền đã rút của người dùng
        $data['withdrawTotal'] = Withdraw::where('user_id', $data['user']->id)->where('status', 1)->sum('withdraw_amount');

        // Tổng số tiền đã gửi của người dùng
        $data['totalDeposit'] = $data['user']->deposits()->where('status', 1)->sum('amount');

        // Tổng số tiền đã đầu tư của người dùng
        $data['totalInvest'] = $data['user']->payments()->where('status', 1)->sum('amount');

        // Tổng số vé mà người dùng đã tạo
        $data['totalTicket'] = $data['user']->tickets->count();

        // Tiêu đề trang
        $data['title'] = "User Details";

        // Trả về view chi tiết người dùng
        return view('backend.users.details')->with($data);
    }

    // Cập nhật thông tin người dùng
    public function userUpdate(AdminUserRequest $request)
    {
        // Gọi dịch vụ để cập nhật thông tin người dùng
        $isSuccess = $this->userservice->update($request);

        // Nếu thành công, trả về thông báo thành công
        if ($isSuccess['type'] === 'success')
            return back()->with('success', $isSuccess['message']);
    }

    // Gửi email cho người dùng
    public function sendUserMail(Request $request, User $user)
    {
        // Xác thực dữ liệu yêu cầu gửi email
        $data = $request->validate([
            'subject' => 'required',
            "message" => 'required',
        ]);

        // Cài đặt các thông tin email
        $data['name'] = $user->username;
        $data['subject'] = $request->subject;
        $data['message'] = $request->message;
        $data['email'] = $user->email;
        $data['username'] = $user->username;
        $data['app_name'] = Helper::config()->appname;

        // Gọi hàm gửi email chung
        Helper::commonMail($data);

        // Trả về thông báo gửi email thành công
        return back()->with('success', 'Send Email To user Successfully');
    }

    // Hiển thị danh sách người dùng bị vô hiệu hóa
    public function disabled(Request $request)
    {
        $title = 'Disabled Users'; // Tiêu đề trang

        // Lọc người dùng theo tìm kiếm nếu có
        $search = $request->search;

        $users = User::when($search, function ($q) use ($search) {
            $q->where('username', 'LIKE', '%' . $search . '%')
                ->orWhere('email', 'LIKE', '%' . $search . '%')
                ->orWhere('mobile', 'LIKE', '%' . $search . '%');
        })->where('status', 0)->latest()->paginate(Helper::pagination());

        // Trả về view với danh sách người dùng bị vô hiệu hóa
        return view('backend.users.index', compact('title', 'users'));
    }

    // Lọc người dùng theo trạng thái (active, deactive, email-unverified, etc.)
    public function userStatusWiseFilter(Request $request)
    {
        $data['title'] = ucwords($request->status) . ' Users'; // Tiêu đề trang

        $users = User::query();

        // Lọc theo các tiêu chí tìm kiếm
        if ($request->search) {
            $users->where(function ($item) use ($request) {
                $item->where('username', $request->search)
                    ->orWhere('email', $request->email)
                    ->orWhere('phone', $request->search);
            });
        }

        // Lọc theo trạng thái người dùng
        if ($request->status == 'active') {
            $users->where('status', 1);
        } elseif ($request->status == 'deactive') {
            $users->where('status', 0);
        } elseif ($request->status === 'email-unverified') {
            $users->where('is_email_verified', 0);
        } elseif ($request->status === 'sms-unverified') {
            $users->where('is_sms_verified', 0);
        } elseif ($request->status === 'kyc-unverified') {
            $users->whereIn('is_kyc_verified', [0, 2]);
        }

        // Trả về view với danh sách người dùng đã lọc
        $data['users'] = $users->paginate(Helper::pagination());

        return view('backend.users.index')->with($data);
    }

    // Hiển thị log hoa hồng người dùng
    public function interestLog()
    {
        $title = "User Interest Log"; // Tiêu đề trang
        $interestLogs = ReferralCommission::latest()->paginate(); // Lấy danh sách log hoa hồng người dùng

        // Trả về view log hoa hồng
        return view('backend.userinterestlog', compact('interestLogs', 'title'));
    }

    // Cập nhật số dư người dùng
    public function userBalanceUpdate(Request $request)
    {
        $request->validate([
            'balance' => 'required|numeric' // Kiểm tra số dư phải là số
        ]);

        // Cập nhật số dư người dùng qua dịch vụ
        $isSuccess = $this->userservice->updateBalance($request);

        // Kiểm tra kết quả và trả về thông báo
        if ($isSuccess['type'] === 'error') {
            return back()->with('error', $isSuccess['message']);
        }

        return back()->with('success', $isSuccess['message']);
    }

    // Đăng nhập vào tài khoản người dùng với tư cách admin
    public function loginAsUser($id)
    {
        $user = User::findOrFail($id); // Lấy thông tin người dùng

        // Đăng nhập vào hệ thống với người dùng đó
        Auth::loginUsingId($user->id);

        return redirect()->route('user.dashboard'); // Chuyển hướng tới dashboard của người dùng
    }

    // Hiển thị danh sách yêu cầu xác minh KYC (Know Your Customer)
    public function kycAll(Request $request)
    {
        $user = User::query(); // Tạo truy vấn cơ bản cho bảng người dùng

        // Lọc theo từ khóa tìm kiếm nếu có
        if ($request->search) {
            $user->where(function ($item) use ($request) {
                $item->where('username', $request->search)
                    ->orWhere('email', $request->email)
                    ->orWhere('phone', $request->search);
            });
        }

        // Lọc theo trạng thái người dùng (active hoặc inactive)
        if ($request->user_status) {
            $status = $request->user_status === 'user_active' ? 1 : 0;
            $user->where('status', $status);
        }

        // Lấy danh sách yêu cầu xác minh KYC
        $data['infos'] = $user->where('is_kyc_verified', 2)->paginate(Helper::pagination());

        $data['title'] = 'KYC Requests'; // Tiêu đề trang

        // Trả về view danh sách yêu cầu KYC
        return view('backend.users.kyc_req')->with($data);
    }

    // Hiển thị chi tiết KYC của người dùng
    public function kycDetails($id)
    {
        $data['user'] = User::findOrFail($id); // Lấy thông tin người dùng

        $data['title'] = 'KYC Details'; // Tiêu đề trang

        // Trả về view chi tiết KYC của người dùng
        return view('backend.users.kyc_details')->with($data);
    }

    // Cập nhật trạng thái KYC của người dùng
    public function kycStatus($status, $id)
    {
        $user = User::findOrFail($id); // Lấy thông tin người dùng

        // Thay đổi trạng thái KYC dựa trên yêu cầu (approve hoặc reject)
        if ($status === 'approve') {
            $user->is_kyc_verified = 1;
        } else {
            $user->is_kyc_verified = 3;
        }

        $user->save(); // Lưu trạng thái mới vào cơ sở dữ liệu

        return back()->with('success', 'Successfull'); // Thông báo thành công
    }

    // Gửi email hàng loạt cho tất cả người dùng
    public function bulkMail(Request $request)
    {
        // Xác thực dữ liệu yêu cầu gửi email
        $data = $request->validate([
            'subject' => 'required',
            'message' => 'required'
        ]);

        // Đưa vào hàng đợi gửi email
        SendEmailJob::dispatch($data);

        return redirect()->route('admin.user.index')->with('success', 'Successfully Send Mail'); // Thông báo gửi email thành công
    }
}
