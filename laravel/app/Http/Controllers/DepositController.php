<?php

namespace App\Http\Controllers; // Định nghĩa namespace cho controller, giúp phân loại mã nguồn

// Import các lớp cần thiết
use App\Helpers\Helper\Helper; // Import helper giúp truy cập các chức năng trợ giúp từ lớp Helper
use App\Models\Gateway; // Import mô hình Gateway, dùng để truy vấn các phương thức thanh toán

class DepositController extends Controller
{
    // Phương thức deposit: hiển thị các cổng thanh toán cho người dùng
    public function deposit()
    {
        // Lấy danh sách các cổng thanh toán đang hoạt động (status = 1) và sắp xếp theo thứ tự mới nhất
        $data['gateways'] = Gateway::where('status', 1)->latest()->get();

        // Đặt tiêu đề cho trang
        $data['title'] = "Payment Methods";

        // Đặt kiểu loại là 'deposit', có thể dùng để phân biệt với các loại giao dịch khác
        $data['type'] = 'deposit';

        // Trả về view hiển thị các cổng thanh toán, sử dụng helper để lấy tên theme
        return view(Helper::theme(). "user.gateway.gateways")->with($data);
    }
}
