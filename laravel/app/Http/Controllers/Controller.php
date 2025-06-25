<?php

namespace App\Http\Controllers; // Định nghĩa namespace cho controller, giúp phân loại mã nguồn

// Import các lớp cần thiết
use Illuminate\Foundation\Auth\Access\AuthorizesRequests; // Lớp để kiểm tra quyền truy cập của người dùng
use Illuminate\Foundation\Bus\DispatchesJobs; // Lớp để xử lý các công việc hàng đợi (Jobs)
use Illuminate\Foundation\Validation\ValidatesRequests; // Lớp để xác thực dữ liệu
use Illuminate\Routing\Controller as BaseController; // Lớp Controller cơ bản của Laravel, làm cha cho các controller khác

class Controller extends BaseController
{
    // Sử dụng các trait giúp thêm tính năng cho controller: 
    // - AuthorizesRequests: Kiểm tra quyền truy cập.
    // - DispatchesJobs: Xử lý các công việc hàng đợi.
    // - ValidatesRequests: Xác thực dữ liệu đầu vào.
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    // Hàm khởi tạo của controller
    function __construct()
    {
        // Lấy giá trị 'locale' từ session
        $locale = session('locale');
        
        // Nếu giá trị 'locale' chưa được thiết lập trong session, mặc định là 'en' (tiếng Anh)
        if ($locale == null) {
            session()->put('locale', 'en'); // Đặt 'locale' mặc định là 'en'
        }
    }
}
