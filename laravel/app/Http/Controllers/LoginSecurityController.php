<?php

namespace App\Http\Controllers;

// Import các lớp cần thiết
use App\Helpers\Helper\Helper;
use App\Models\Configuration;
use App\Models\LoginSecurity;
use Auth;
use Hash;
use Illuminate\Http\Request;

class LoginSecurityController extends Controller
{
    // Hiển thị form cấu hình bảo mật 2FA
    public function show2faForm(Request $request)
    {
        $general = Configuration::first(); // Lấy cấu hình chung từ DB

        $data['title'] = '2FA Settings'; // Tiêu đề trang
        $user = Auth::user(); // Lấy người dùng hiện tại
        $google2fa_url = "";
        $secret_key = "";

        // Kiểm tra người dùng đã có cấu hình 2FA chưa
        if ($user->loginSecurity()->exists()) {
            $google2fa = (new \PragmaRX\Google2FAQRCode\Google2FA()); // Khởi tạo đối tượng Google2FA
            $google2fa_url = $google2fa->getQRCodeInline(
                $general->appname, // Tên ứng dụng
                $user->email, // Email người dùng
                $user->loginSecurity->google2fa_secret // Mã bí mật đã lưu
            );
            $secret_key = $user->loginSecurity->google2fa_secret;
        }

        // Truyền dữ liệu sang view
        $data['user'] = $user;
        $data['secret'] = $secret_key;
        $data['google2fa_url'] = $google2fa_url;

        return view(Helper::theme().'user.2fa_settings')->with($data); // Trả về view 2FA settings
    }

    /**
     * Tạo mã secret cho 2FA (sử dụng khi kích hoạt)
     */
    public function generate2faSecret(Request $request)
    {
        $user = Auth::user(); // Lấy người dùng hiện tại

        // Khởi tạo Google2FA
        $google2fa = (new \PragmaRX\Google2FAQRCode\Google2FA());

        // Tạo hoặc lấy bản ghi bảo mật đăng nhập của người dùng
        $login_security = LoginSecurity::firstOrNew(array('user_id' => $user->id));
        $login_security->user_id = $user->id;
        $login_security->google2fa_enable = 0; // Chưa kích hoạt
        $login_security->google2fa_secret = $google2fa->generateSecretKey(); // Tạo mã bí mật mới
        $login_security->save();

        return redirect()->route('user.2fa')->with('success', "Secret key is generated.");
    }

    /**
     * Kích hoạt 2FA sau khi người dùng nhập mã xác minh
     */
    public function enable2fa(Request $request)
    {
        $user = Auth::user();
        $google2fa = (new \PragmaRX\Google2FAQRCode\Google2FA());

        $secret = $request->input('secret'); // Mã từ ứng dụng Google Authenticator
        $valid = $google2fa->verifyKey($user->loginSecurity->google2fa_secret, $secret); // Xác minh mã

        if ($valid) {
            // Nếu hợp lệ thì bật 2FA
            $user->loginSecurity->google2fa_enable = 1;
            $user->loginSecurity->save();
            return redirect()->route('user.2fa')->with('success', "2FA is enabled successfully.");
        } else {
            // Mã không đúng
            return redirect()->route('user.2fa')->with('error', "Invalid verification Code, Please try again.");
        }
    }

    /**
     * Vô hiệu hóa 2FA
     */
    public function disable2fa(Request $request)
    {
        // Kiểm tra mật khẩu hiện tại của người dùng có đúng không
        if (!(Hash::check($request->get('current-password'), Auth::user()->password))) {
            return redirect()->back()->with("error", "Your password does not matches with your account password. Please try again.");
        }

        // Xác thực yêu cầu
        $validatedData = $request->validate([
            'current-password' => 'required',
        ]);

        // Vô hiệu hóa 2FA
        $user = Auth::user();
        $user->loginSecurity->google2fa_enable = 0;
        $user->loginSecurity->save();

        return redirect()->route('user.2fa')->with('success', "2FA is now disabled.");
    }
}
