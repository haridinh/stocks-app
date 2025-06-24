<?php

// Đặt controller trong namespace Auth
namespace App\Http\Controllers\Auth;

// Import các lớp cần thiết
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Socialite; // Dùng cho đăng nhập mạng xã hội
use Auth;
use Exception;

// Controller xử lý đăng nhập bằng Google
class GoogleController extends Controller
{
    // Phương thức chuyển hướng người dùng đến trang đăng nhập Google
    public function redirectToGoogle()
    {
        return Socialite::driver('google')->redirect(); // Gọi driver Google để thực hiện OAuth
    }

    // Phương thức xử lý callback sau khi người dùng đăng nhập Google
    public function handleGoogleCallback()
    {
        try {
            // Lấy thông tin người dùng từ Google trả về
            $user = Socialite::driver('google')->user();

            // Kiểm tra xem email này đã tồn tại trong hệ thống chưa
            $finduser = User::where('email', $user->email)->first();

            if ($finduser) {
                // Nếu đã tồn tại, đăng nhập luôn
                Auth::login($finduser);
                return redirect()->route('user.dashboard');
            } else {
                // Nếu chưa tồn tại, tạo tài khoản mới
                $newUser = User::create([
                    'username'   => $user->name,
                    'email'      => $user->email,
                    'google_id'  => $user->id, // Lưu ID Google
                    'password'   => encrypt('123456'), // Gán mật khẩu mặc định (mã hóa)
                    'status'     => 1,  // Kích hoạt tài khoản
                    'ev'         => 1   // Xác minh email luôn
                ]);

                // Đăng nhập luôn sau khi tạo
                Auth::login($newUser);

                return redirect()->route('user.dashboard');
            }

        } catch (Exception $e) {
            // Xảy ra lỗi, chuyển hướng về trang đăng nhập với thông báo lỗi
            return redirect()->route('user.login')->with('error', 'Something Went Wrong');
        }
    }
}