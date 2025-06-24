<?php

// Khai báo namespace và import các lớp cần thiết
namespace App\Http\Controllers\Auth;

use App\Helpers\Helper\Helper;
use App\Http\Controllers\Controller;
use App\Models\Configuration;
use App\Models\GeneralSetting;
use App\Models\Template;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Cache;

// Controller xử lý chức năng Quên Mật Khẩu và Xác Thực
class ForgotPasswordController extends Controller
{
    // Hiển thị form nhập email để lấy lại mật khẩu
    public function index()
    {
        $data['title'] = 'Forgot Password';
        return view(Helper::theme().'auth.email')->with($data); // Trả về view quên mật khẩu
    }

    // Gửi mã xác thực về email người dùng
    public function sendVerification(Request $request)
    {
        $general = Configuration::first(); // Lấy cấu hình hệ thống

        // Validate email và reCAPTCHA nếu được bật
        $request->validate([
            'email' => 'required|email',
            'g-recaptcha-response' => Rule::requiredIf($general->allow_recaptcha == 1)
        ],[
            'g-recaptcha-response.required' => 'You Have To fill recaptcha'
        ]);

        // Tìm người dùng theo email
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return back()->with('error', 'Please Provide a valid Email'); // Không tìm thấy user
        }

        // Tạo mã xác thực ngẫu nhiên 6 chữ số
        $code = random_int(100000, 999999);
        $user->email_verification_code = $code;
        $user->save();

        // Lấy template email có tên 'password_reset' nếu đang hoạt động
        $template = Template::where('name', 'password_reset')->where('status', 1)->first();

        // Gửi email nếu có template
        if ($template) {
            Helper::fireMail([
                'username' => $user->username,
                'email' => $user->email,
                'app_name' => Helper::config()->appname,
                'code' => $code
            ], $template);
        }

        // Lưu email vào session để xác minh mã
        session()->put('email', $user->email);

        return redirect()->route('user.auth.verify')->with('success', 'Send verification code to your email');
    }

    // Hiển thị form nhập mã xác thực (verify code)
    public function verify()
    {
        $email = session('email'); // Lấy email từ session
        $title = 'Verify Code';

        $user = User::where('email', $email)->first();

        if (!$user) {
            return redirect()->route('user.forgot.password'); // Nếu không có user, quay lại trang quên mật khẩu
        }

        return view(Helper::theme().'auth.verify', compact('title', 'email'));
    }

    // Xử lý xác minh mã xác thực người dùng nhập
    public function verifyCode(Request $request)
    {
        $general = Configuration::first();

        // Validate dữ liệu
        $request->validate([
            'code' => 'required',
            'email' => 'required|email|exists:users,email',
            'g-recaptcha-response' => Rule::requiredIf($general->allow_recaptcha == 1)
        ],[
            'g-recaptcha-response.required' => 'You Have To fill recaptcha'
        ]);

        $user = User::where('email', $request->email)->first();
        $token = $user->email_verification_code;

        // So sánh mã xác thực
        if ($user->email_verification_code != $request->code) {
            $user->email_verification_code = null; // Hủy mã cũ
            $user->save();
            return back()->with('error','Invalid Code'); // Sai mã
        }

        $user->email_verification_code = null; // Mã đúng, xóa mã
        $user->save();

        // Lưu thông tin xác thực vào session để cho phép đặt lại mật khẩu
        session()->put('identification', [
            "token" => $token,
            "email" => $user->email
        ]);

        return redirect()->route('user.reset.password'); // Chuyển đến trang reset password
    }

    // Hiển thị form đặt lại mật khẩu mới
    public function reset()
    {
        $session = session('identification');

        if (!$session) {
            return redirect()->route('user.login'); // Không có session, quay về đăng nhập
        }

        $title = 'Reset Password';
        return view(Helper::theme().'auth.reset', compact('title', 'session'));
    }

    // Xử lý đặt lại mật khẩu mới
    public function resetPassword(Request $request)
    {
        $general = Configuration::first();

        // Validate dữ liệu
        $request->validate([
            'email' => 'required|email|exists:users,email', 
            'password' => 'required|confirmed', // phải nhập lại mật khẩu
            'g-recaptcha-response' => Rule::requiredIf($general->allow_recaptcha == 1)
        ],[
            'g-recaptcha-response.required' => 'You Have To fill recaptcha'
        ]);

        $user = User::where('email', $request->email)->first();

        // Mã hóa mật khẩu mới và lưu
        $user->password = bcrypt($request->password);
        $user->save();

        return redirect()->route('user.login')->with('success', 'Successfully Reset Your Password');
    }

    // Hiển thị giao diện xác thực email/SMS nếu tài khoản chưa xác minh
    public function verifyAuth()
    {
        // Nếu đã xác minh email (ev) và SMS (sv) thì chuyển hướng tới dashboard
        if(auth()->user()->ev && auth()->user()->sv){
            return redirect()->route('user.dashboard');
        }

        $title = 'Verify Account';
        return view(Helper::theme().'auth.email_sms_verify', compact('title'));
    }

    // Xác minh mã xác thực gửi qua email
    public function verifyEmailAuth(Request $request)
    {
        $user = auth()->user();

        $request->validate(['code' => 'required']);

        if ($user->email_verification_code != $request->code) {
            return redirect()->back()->with('error', 'Invalid Verification Code'); // Mã không đúng
        }

        // Đánh dấu đã xác minh email
        $user->email_verification_code = null;
        $user->is_email_verified = 1;
        $user->save();

        return redirect()->route('user.dashboard');
    }

    // Xác minh mã xác thực gửi qua SMS
    public function verifySmsAuth(Request $request)
    {
        $user = auth()->user();

        $request->validate(['code' => 'required']);

        if ($user->sms_verification_code != $request->code) {
            return redirect()->back()->with('error', 'Invalid Verification Code'); // Mã sai
        }

        // Đánh dấu đã xác minh số điện thoại
        $user->sms_verification_code = null;
        $user->sv = 1;
        $user->save();

        return redirect()->route('user.dashboard');
    }
}