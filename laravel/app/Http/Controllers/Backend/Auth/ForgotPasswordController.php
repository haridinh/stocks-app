<?php

// Đặt controller trong namespace dành cho Auth ở phần backend (admin)
namespace App\Http\Controllers\Backend\Auth;

// Import các class, model và service cần thiết
use App\Http\Controllers\Controller;
use App\Http\Requests\AdminForgotPasswordRequest; // Form Request để validate dữ liệu quên mật khẩu
use App\Models\Admin;
use App\Models\AdminPasswordReset;               // Bảng tạm lưu mã khôi phục mật khẩu
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;
use App\Models\GeneralSetting;
use App\Services\AdminForgotPasswordService;     // Service xử lý logic quên mật khẩu cho admin

// Controller xử lý luồng quên mật khẩu cho admin
class ForgotPasswordController extends Controller
{
    // Thuộc tính chứa service quên mật khẩu
    protected $forgotPassword;

    // Constructor – inject service & gắn middleware bảo vệ route
    public function __construct(AdminForgotPasswordService $forgotPassword)
    {
        $this->forgotPassword = $forgotPassword;

        // middleware ngăn admin đã đăng nhập truy cập các route này
        $this->middleware('admin.guest');
    }

    // Hiển thị form nhập email để yêu cầu mã khôi phục
    public function showLinkRequestForm()
    {
        $data['title'] = 'Account Recovery'; // Tiêu đề trang

        // Xóa sạch bảng reset trước đó để tránh mã lỗi thời
        AdminPasswordReset::truncate();

        // Trả về view giao diện quên mật khẩu
        return view('backend.auth.forgot-password')->with($data);
    }

    // Trả về "password broker" dành riêng cho bảng admin
    public function broker()
    {
        return Password::broker('admins'); // Dùng cấu hình broker trong config/auth.php
    }

    // Gửi mã khôi phục đến email admin
    public function sendResetCodeEmail(AdminForgotPasswordRequest $request)
    {
        // Gọi service thực hiện gửi mã xác minh
        $isFired = $this->forgotPassword->forgot($request);

        // Nếu có lỗi, quay lại với thông báo lỗi
        if($isFired['type'] === 'error'){
            return back()->with('error', $isFired['message']);
        }

        // Thành công → chuyển đến trang nhập mã xác nhận
        return redirect()->route('admin.password.verify.code')->with('success', $isFired['message']);
    }

    // Hiển thị form để người dùng nhập mã xác minh
    public function verifyCodeForm(Request $request)
    {
        $data['title'] = __('Code Verify'); // Tiêu đề

        return view('backend.auth.code_verify')->with($data);
    }

    // Xử lý việc xác thực mã đã gửi qua email
    public function verifyCode(AdminForgotPasswordRequest $request)
    {
        // So sánh mã người dùng nhập với mã lưu trong session
        $code = session('code') == $request->code ? true : false;

        if ($code) {
            // Mã hợp lệ → chuyển sang form đặt lại mật khẩu
            return redirect()->route('admin.password.reset.form', $request->code)
                             ->with('success', 'Now you can reset your Password');
        }

        // Mã sai → hiển thị lỗi
        return back()->with('error', 'Verification Code did not match');
    }
}