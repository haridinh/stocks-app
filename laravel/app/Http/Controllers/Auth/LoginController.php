<?php

// Namespace cho nhóm Auth Controllers
namespace App\Http\Controllers\Auth;

// Import các class và service cần thiết
use App\Helpers\Helper\Helper;
use App\Http\Controllers\Controller;
use App\Http\Requests\UserLoginRequest; // Request tùy chỉnh để validate login
use App\Services\UserLogin;             // Service chứa logic đăng nhập
use Illuminate\Support\Facades\Auth;

// Controller xử lý đăng nhập người dùng
class LoginController extends Controller
{
    // Thuộc tính lưu instance của service login
    protected $login;

    // Hàm khởi tạo, inject UserLogin service
    public function __construct(UserLogin $login)
    {
        $this->login = $login;
    }

    // Hiển thị giao diện đăng nhập
    public function index()
    {
        $data['title'] = 'Login Page'; // Tiêu đề trang

        $data['content'] = Helper::builder('auth'); // Có thể là nội dung động cho trang (vd: logo, text...)

        // Trả về view đăng nhập tùy theo giao diện đang được sử dụng
        return view(Helper::theme() . 'auth.login')->with($data);
    }

    // Xử lý đăng nhập người dùng
    public function login(UserLoginRequest $request)
    {
        // Gọi service UserLogin để xử lý đăng nhập, trả về kết quả
        $isSuccess = $this->login->login($request);

        if ($isSuccess['type'] == 'error') {
            // Nếu có lỗi, redirect lại form đăng nhập với thông báo lỗi
            return redirect()->route('user.login')->with('error', $isSuccess['message']);
        }

        // Đăng nhập thành công, chuyển hướng về dashboard
        return redirect()->route('user.dashboard')->with('success', $isSuccess['message']);
    }

    // Đăng xuất người dùng
    public function signOut()
    {
        Auth::logout(); // Xóa phiên đăng nhập

        return Redirect()->route('user.login'); // Quay về trang đăng nhập
    }
}