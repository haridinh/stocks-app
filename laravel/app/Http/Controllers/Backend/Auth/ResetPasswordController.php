<?php

// Namespace cho controller đặt lại mật khẩu của admin trong backend
namespace App\Http\Controllers\Backend\Auth;

// Import các class cần thiết
use App\Http\Controllers\Controller;
use App\Http\Requests\AdminResetPasswordRequest; // Form Request kiểm tra dữ liệu đặt lại mật khẩu
use App\Models\AdminPasswordReset;              // Model lưu thông tin token reset mật khẩu
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Http\Request;
use App\Services\AdminForgotPasswordService;    // Service xử lý logic reset password

class ResetPasswordController extends Controller
{
    // Trang sẽ chuyển hướng đến sau khi reset thành công
    public $redirectTo = '/admin/home';

    // Thuộc tính chứa instance của service reset mật khẩu
    protected $password;

    // Constructor – inject service & gắn middleware
    public function __construct(AdminForgotPasswordService $password)
    {
        $this->password = $password;

        // Middleware để chặn admin đã đăng nhập vào được trang này
        $this->middleware('admin.guest');
    }

    /**
     * Hiển thị form đặt lại mật khẩu
     *
     * @param Request $request
     * @param string $token – mã token reset mật khẩu từ email
     */
    public function showResetForm(Request $request, $token)
    {
        $title = "Account Recovery"; // Tiêu đề trang

        // Tìm token hợp lệ và chưa dùng (status = 0)
        $resetToken = AdminPasswordReset::where('token', $token)
                                        ->where('status', 0)
                                        ->first();

        // Nếu token không tồn tại hoặc đã hết hạn
        if (!$resetToken) {
            return redirect()->route('admin.password.reset')->with(['error', 'Token not found!']);
        }

        $email = $resetToken->email; // Lấy email để truyền vào view

        return view('backend.auth.reset', compact('title', 'email', 'token'));
    }

    /**
     * Gửi lại mã xác nhận đặt lại mật khẩu
     */
    public function sendAgain()
    {
        $isSuccess = $this->password->sendAgain(); // Gọi service để gửi lại mã

        if ($isSuccess['type'] === 'error') {
            return back()->with('error', $isSuccess['message']);
        }

        return back()->with('success', $isSuccess['message']);
    }

    /**
     * Xử lý việc đặt lại mật khẩu khi người dùng gửi form
     *
     * @param AdminResetPasswordRequest $request
     */
    public function reset(AdminResetPasswordRequest $request)
    {
        // Gọi service để thực hiện đặt lại mật khẩu
        $isSuccess = $this->password->reset($request);

        if ($isSuccess['type'] === 'error') {
            return redirect()->route('admin.login')->with('error', $isSuccess['message']);
        }

        return redirect()->route('admin.login')->with('success', $isSuccess['message']);
    }

    /**
     * Trả về broker cho guard admin
     */
    public function broker()
    {
        return Password::broker('admins');
    }

    /**
     * Trả về guard xác thực cho admin
     */
    protected function guard()
    {
        return Auth::guard('admin');
    }
}