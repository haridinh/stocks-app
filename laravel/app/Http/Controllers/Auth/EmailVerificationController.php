<?php

// Khai báo namespace cho Controller này thuộc App\Http\Controllers\Auth
namespace App\Http\Controllers\Auth;

// Import các lớp cần thiết từ các namespace khác
use App\Helpers\Helper\Helper;  // Import helper để sử dụng các phương thức hỗ trợ
use App\Http\Controllers\Controller;  // Import lớp Controller, lớp cha của mọi controller trong Laravel
use App\Http\Requests\EmailVerificationRequest;  // Import request để xác thực dữ liệu từ form gửi lên
use App\Services\EmailVerification;  // Import dịch vụ xử lý xác thực email

// Định nghĩa controller để xử lý các yêu cầu xác thực email
class EmailVerificationController extends Controller
{
    // Khai báo biến lưu trữ dịch vụ xác thực email
    protected $verify;

    // Constructor: hàm khởi tạo, được gọi khi controller được tạo ra
    public function __construct(EmailVerification $verifcation)
    {
        // Gán dịch vụ xác thực email vào biến $verify
        $this->verify = $verifcation;
    }

    // Phương thức hiển thị giao diện xác thực email
    public function emailVerify()
    {
        // Tạo biến dữ liệu title cho view
        $data['title'] = "Email Verify";  // Tiêu đề trang xác thực email

        // Trả về view email xác thực, sử dụng helper để lấy theme và đường dẫn view
        return view(Helper::theme() . 'auth.email_sms_verify');
    }

    // Phương thức xử lý yêu cầu xác thực email khi người dùng gửi form
    public function emailVerifyConfirm(EmailVerificationRequest $request)
    {
        // Gọi dịch vụ xác thực email với dữ liệu gửi từ form
        $isSucces = $this->verify->verify($request);

        // Kiểm tra kết quả xác thực
        if ($isSucces['type'] === 'success') {
            // Nếu thành công, chuyển hướng đến trang dashboard người dùng và thông báo thành công
            return  redirect()->route('user.dashboard')->with('success', $isSucces['message']);
        }

        // Nếu không thành công, quay lại trang trước và thông báo lỗi
        return  redirect()->back()->with('error', $isSucces['message']);
    }
}