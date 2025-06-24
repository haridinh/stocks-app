<?php

// Đặt controller trong namespace xử lý xác thực (Auth) cho phía backend (Admin)
namespace App\Http\Controllers\Backend\Auth;

// Import các class cần thiết
use App\Http\Controllers\Controller;
use App\Http\Requests\AdminLoginRequest;   // Form Request để kiểm tra dữ liệu đăng nhập
use App\Models\Admin;
use App\Services\AdminLoginService;        // Service xử lý logic đăng nhập admin

class LoginController extends Controller
{
    // Thuộc tính chứa instance của service đăng nhập admin
    protected $login;

    // Hàm khởi tạo (constructor)
    public function __construct(AdminLoginService $login)
    {
        $this->login = $login;

        // Middleware ngăn admin đã đăng nhập truy cập lại trang login (trừ hàm logout)
        $this->middleware('admin.guest')->except('logout');
    }

    // Hiển thị trang đăng nhập
    public function loginPage()
    {
        $data['title'] = __('Admin Login Page'); // Tiêu đề trang

        return view('backend.auth.login')->with($data); // Trả về view đăng nhập admin
    }

    // Xử lý khi người dùng submit form đăng nhập
    public function login(AdminLoginRequest $request)
    {
        // Gọi service để lấy dữ liệu cần kiểm tra từ request
        [$data, $remember] = $this->login->validateData($request);

        // Dùng guard 'admin' để xác thực đăng nhập
        if(auth()->guard('admin')->attempt($data, $remember)){
            // Đăng nhập thành công
            return redirect()->route('admin.home')->with('success', 'Login Successful');
        }

        // Đăng nhập thất bại
        return redirect()->route('admin.login')->with('error', 'Invalid Credentials');
    }

    // Đăng xuất admin khỏi hệ thống
    public function logout()
    {
        auth()->guard('admin')->logout(); // Gọi guard 'admin' để logout

        return redirect()->route('admin.login')->with('success', 'Logout Successful');
    }
}