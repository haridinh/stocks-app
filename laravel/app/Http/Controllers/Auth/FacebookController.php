<?php

// Khai báo namespace của controller này thuộc Auth (xác thực)
namespace App\Http\Controllers\Auth;

// Import các lớp cần thiết
use App\Http\Controllers\Controller;  // Lớp Controller cơ sở trong Laravel
use Illuminate\Http\Request;  // Lớp Request để nhận và xử lý yêu cầu HTTP
use Laravel\Socialite\Facades\Socialite;  // Socialite facade để tương tác với dịch vụ đăng nhập xã hội (Facebook)
use Exception;  // Lớp Exception để xử lý lỗi
use App\Models\User;  // Model User để thao tác với bảng người dùng trong cơ sở dữ liệu
use Illuminate\Support\Facades\Auth;  // Facade Auth để đăng nhập và kiểm tra người dùng

// Định nghĩa Controller xử lý xác thực qua Facebook
class FacebookController extends Controller
{
    // Phương thức này sẽ chuyển hướng người dùng đến trang đăng nhập của Facebook
    public function redirectToFacebook()
    {
        return Socialite::driver('facebook')->redirect();
    }

    // Phương thức này sẽ xử lý callback từ Facebook sau khi người dùng đăng nhập thành công
    public function handleFacebookCallback()
    {
        try {
            // Lấy thông tin người dùng từ Facebook sau khi họ cho phép ứng dụng truy cập
            $user = Socialite::driver('facebook')->user();

            // Kiểm tra xem email của người dùng đã tồn tại trong hệ thống chưa
            $finduser = User::where('email', $user->email)->first();
        
            if($finduser){
                // Nếu người dùng đã có tài khoản, đăng nhập người dùng đó
                Auth::login($finduser);
        
                // Chuyển hướng đến trang dashboard của người dùng
                return redirect()->route('user.dashboard');
            } else {
                // Nếu người dùng chưa có tài khoản, tạo mới một tài khoản với thông tin từ Facebook
                $newUser = User::create([
                    'username' => $user->name,  // Tên người dùng từ Facebook
                    'email' => $user->email,  // Email người dùng từ Facebook
                    'facebook_id'=> $user->id,  // ID Facebook của người dùng
                    'password' => encrypt('123456'),  // Mật khẩu mặc định (được mã hóa) cho người dùng mới
                    'status' => 1  // Trạng thái người dùng (1 có thể nghĩa là "hoạt động")
                ]);

                // Đăng nhập người dùng mới
                Auth::login($newUser);
        
                // Chuyển hướng đến trang dashboard của người dùng
                return redirect()->route('user.dashboard');
            }
        
        } catch (Exception $e) {
            // Nếu có lỗi trong quá trình xử lý (ví dụ: người dùng từ chối đăng nhập), quay lại trang login và thông báo lỗi
            return redirect()->route('user.login')->with('error','Something Went Wrong');
        }
    }
}