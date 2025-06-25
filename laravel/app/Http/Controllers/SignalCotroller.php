<?php

namespace App\Http\Controllers;

// Các thư viện và lớp cần thiết
use App\Helpers\Helper\Helper;          // Lớp Helper để lấy các phương thức trợ giúp chung (như pagination, theme...)
use App\Models\DashboardSignal;         // Mô hình dữ liệu cho các tín hiệu của dashboard, liên kết với người dùng
use App\Models\Signal;                  // Mô hình dữ liệu cho các tín hiệu (signal) cần hiển thị
use Illuminate\Http\Request;            // Lớp Request của Laravel để xử lý các yêu cầu HTTP

class SignalController extends Controller
{
    // Hàm xử lý việc hiển thị tất cả các tín hiệu (signals) cho người dùng
    public function allSignals(Request $request)
    {
        // Cấu hình tiêu đề cho trang
        $data['title'] = 'All Signals';

        // Lấy tất cả các signal_id liên kết với user hiện tại từ bảng DashboardSignal
        // Chỉ lấy các tín hiệu mà người dùng đã quan tâm (dựa trên user_id)
        $dashboardSignal = DashboardSignal::where('user_id', auth()->id())->pluck('signal_id');

        // Lọc tín hiệu theo từ khóa tìm kiếm nếu có (search) và lấy những tín hiệu có trong danh sách của dashboardSignal
        $data['signals'] = Signal::when($request->search, function ($item) use ($request) {
            $item->where(function ($item) use ($request) {
                // Lọc tín hiệu theo ID hoặc tên (title) của tín hiệu dựa trên từ khóa tìm kiếm
                $item->where('id', $request->search)
                    ->orWhere('title', 'LIKE', '%' . $request->search . '%');
            });
        })
        // Lọc tín hiệu theo danh sách signal_id có trong bảng DashboardSignal
        ->whereIn('id', $dashboardSignal)
        // Sắp xếp các tín hiệu theo thứ tự mới nhất
        ->latest()
        // Tải thêm các mối quan hệ cần thiết (plans, pair, time, market)
        ->with('plans', 'pair', 'time', 'market')
        // Phân trang kết quả theo cấu hình phân trang trong Helper
        ->paginate(Helper::pagination());

        // Trả về view hiển thị danh sách tín hiệu
        return view(Helper::theme() . 'user.signals')->with($data);
    }

    // Hàm xử lý việc hiển thị chi tiết tín hiệu (signal) theo ID
    public function details($id)
    {
        // Lấy chi tiết tín hiệu theo ID (nếu không tìm thấy sẽ báo lỗi 404)
        $data['signal'] = Signal::findOrFail($id);

        // Cấu hình tiêu đề cho trang
        $data['title'] = 'Signal Description';

        // Trả về view chi tiết tín hiệu với dữ liệu tín hiệu
        return view(Helper::theme() . 'user.signal_details')->with($data);
    }
}
