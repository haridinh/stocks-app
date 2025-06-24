<?php

// Đặt controller vào namespace Auth (nhóm các controller xác thực)
namespace App\Http\Controllers\Auth;

// Import các class cần thiết
use App\Helpers\Helper\Helper;
use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;     // Lớp Form Request để kiểm tra dữ liệu đầu vào
use App\Services\UserRegistration;         // Service xử lý logic đăng ký người dùng

// Controller xử lý đăng ký người dùng
class RegistrationController extends Controller
{
    // Thuộc tính chứa instance của service đăng ký
    protected $register; 

    // Hàm khởi tạo - Dependency Injection service UserRegistration
    public function __construct(UserRegistration $register)
    {
        $this->register = $register;
    }

    // Hiển thị form đăng ký người dùng
    public function index()
    {
        $data['title'] = 'Register User'; // Tiêu đề trang

        $data['content'] = Helper::builder('auth'); // Có thể là nội dung động (logo, hướng dẫn...)

        // Trả về view giao diện đăng ký theo theme hiện tại
        return view(Helper::theme() . 'auth.register')->with($data);
    }

    // Xử lý khi người dùng submit form đăng ký
    public function register(RegisterRequest $request)
    {
        // Gọi service UserRegistration để thực hiện đăng ký
        $isSuccess = $this->register->register($request);

        // Nếu có lỗi trong quá trình đăng ký
        if($isSuccess['type'] === 'error'){
            return redirect()->back()->with('error', $isSuccess['message']);
        }

        // Đăng ký thành công, chuyển hướng đến dashboard
        return redirect()->route('user.dashboard')->with('success', $isSuccess['message']);
    }
}