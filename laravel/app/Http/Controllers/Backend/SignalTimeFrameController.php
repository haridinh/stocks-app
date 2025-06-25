<?php

namespace App\Http\Controllers\Backend; // Định nghĩa namespace cho controller, giúp phân loại mã nguồn

// Import các lớp và helper cần thiết
use App\Helpers\Helper\Helper; // Lớp Helper chứa các hàm tiện ích, ví dụ: phân trang
use App\Http\Controllers\Controller; // Lớp Controller cơ bản của Laravel
use App\Models\TimeFrame; // Lớp TimeFrame, sử dụng để làm việc với bảng thời gian (Time Frames)
use Illuminate\Http\Request; // Lớp Request của Laravel để nhận dữ liệu từ client

class SignalTimeFrameController extends Controller
{
    // Hàm hiển thị trang quản lý các khung thời gian (Time Frames)
    public function index(Request $request)
    {
        // Đặt tiêu đề trang
        $data['title'] = 'Manage Time Frame';

        // Lấy danh sách các khung thời gian theo tìm kiếm và phân trang
        $data['frames'] = TimeFrame::search($request->search) // Tìm kiếm khung thời gian theo từ khóa
            ->latest() // Sắp xếp theo thứ tự mới nhất
            ->paginate(Helper::pagination()); // Phân trang, sử dụng hàm pagination từ Helper

        // Trả về view 'backend.frame.index' với dữ liệu đã xử lý
        return view('backend.frame.index')->with($data);
    }

    // Hàm xử lý việc tạo một khung thời gian mới
    public function store(Request $request)
    {
        // Xác thực dữ liệu nhận được từ request
        $data = $request->validate([
            'name' => 'required|max:255|unique:time_frames,name', // Tên khung thời gian phải có và không trùng lặp trong bảng time_frames
            'status' => 'required|in:0,1' // Trạng thái phải là 0 (không hoạt động) hoặc 1 (hoạt động)
        ]);

        // Tạo một khung thời gian mới với dữ liệu đã xác thực
        TimeFrame::create($data);

        // Quay lại trang trước đó với thông báo thành công
        return redirect()->back()->with('success', 'Time Frame created successfully');
    }

    // Hàm xử lý việc cập nhật khung thời gian
    public function update(Request $request, $id)
    {
        // Lấy khung thời gian theo ID
        $frame = TimeFrame::findOrFail($id);

        // Xác thực dữ liệu nhận được từ request
        $data = $request->validate([
            'name' => 'required|max:255|unique:time_frames,name,' . $frame->id, // Tên khung thời gian phải có và không trùng lặp (ngoại trừ khung thời gian hiện tại)
            'status' => 'required|in:0,1' // Trạng thái phải là 0 hoặc 1
        ]);

        // Cập nhật khung thời gian với dữ liệu đã xác thực
        $frame->update($data);

        // Quay lại trang trước đó với thông báo thành công
        return redirect()->back()->with('success', 'Frame updated successfully');
    }

    // Hàm xử lý việc xóa một khung thời gian
    public function destroy($id)
    {
        // Lấy khung thời gian theo ID
        $frame = TimeFrame::findOrFail($id);

        // Xóa khung thời gian
        $frame->delete();

        // Quay lại trang trước đó với thông báo thành công
        return redirect()->back()->with('success', 'Frame Deleted successfully');
    }

    // Hàm thay đổi trạng thái của khung thời gian
    public function changeStatus($id)
    {
        // Lấy khung thời gian theo ID
        $frame = TimeFrame::findOrFail($id);

        // Nếu trạng thái hiện tại là 1 (hoạt động), đổi thành 0 (không hoạt động), ngược lại
        if ($frame->status) {
            $frame->status = false; // Đặt trạng thái là không hoạt động
        } else {
            $frame->status = true; // Đặt trạng thái là hoạt động
        }

        // Lưu lại thay đổi trạng thái
        $frame->save();

        // Trả về phản hồi JSON với thông báo thay đổi trạng thái thành công
        $notify = ['success' => 'Status Change Successfully'];

        return response()->json($notify);
    }
}
