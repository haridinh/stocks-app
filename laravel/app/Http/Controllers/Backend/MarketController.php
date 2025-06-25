<?php

namespace App\Http\Controllers\Backend; // Đặt namespace cho controller này vào nhóm Backend

use App\Helpers\Helper\Helper; // Sử dụng Helper từ thư mục Helpers để hỗ trợ các chức năng tiện ích
use App\Http\Controllers\Controller; // Kế thừa controller cơ bản của Laravel
use App\Models\Market; // Mô hình quản lý các loại thị trường (Market)
use Illuminate\Http\Request; // Lớp Request của Laravel để xử lý các yêu cầu HTTP

class MarketController extends Controller // Controller xử lý các thao tác liên quan đến loại thị trường
{
    // Hiển thị danh sách các loại thị trường
    public function index(Request $request)
    {
        $data['title'] = 'Manage Market Type'; // Tiêu đề trang

        // Tìm kiếm các loại thị trường theo tên và phân trang
        $data['markets'] = Market::search($request->search) // Tìm kiếm theo từ khóa tìm kiếm trong request
            ->latest() // Lấy các kết quả mới nhất
            ->paginate(Helper::pagination()); // Phân trang với số lượng tối đa được cấu hình trong Helper

        // Trả về view để hiển thị danh sách thị trường
        return view('backend.market.index')->with($data);
    }

    // Tạo một loại thị trường mới
    public function store(Request $request)
    {
        // Xác thực dữ liệu đầu vào từ người dùng
        $data = $request->validate([
            'name' => 'required|unique:markets,name|max:255', // Tên phải duy nhất trong bảng markets, độ dài tối đa 255 ký tự
            'status' => 'required|in:0,1' // Trạng thái phải là 0 hoặc 1
        ]);

        // Tạo một bản ghi mới trong bảng markets
        Market::create($data);

        // Quay lại trang trước với thông báo thành công
        return redirect()->back()->with('success', 'Market Type created successfully');
    }

    // Cập nhật thông tin loại thị trường
    public function update(Request $request, $id)
    {
        // Tìm loại thị trường theo ID hoặc trả về lỗi nếu không tìm thấy
        $pair = Market::findOrFail($id);

        // Xác thực dữ liệu đầu vào từ người dùng
        $data = $request->validate([
            'name' => 'required|max:255|unique:markets,name,' . $pair->id, // Tên phải duy nhất trừ bản ghi hiện tại
            'status' => 'required|in:0,1' // Trạng thái phải là 0 hoặc 1
        ]);

        // Cập nhật thông tin loại thị trường
        $pair->update($data);

        // Quay lại trang trước với thông báo thành công
        return redirect()->back()->with('success', 'Market Type updated successfully');
    }

    // Xóa loại thị trường
    public function destroy($id)
    {
        // Tìm loại thị trường theo ID hoặc trả về lỗi nếu không tìm thấy
        $pair = Market::findOrFail($id);

        // Xóa loại thị trường
        $pair->delete();

        // Quay lại trang trước với thông báo thành công
        return redirect()->back()->with('success', 'Market Type Deleted successfully');
    }

    // Thay đổi trạng thái của loại thị trường (Kích hoạt/Tắt)
    public function changeStatus($id)
    {
        // Tìm loại thị trường theo ID hoặc trả về lỗi nếu không tìm thấy
        $pair = Market::findOrFail($id);

        // Đảo ngược trạng thái của thị trường (0 -> 1 hoặc 1 -> 0)
        if ($pair->status) {
            $pair->status = false; // Nếu đang ở trạng thái 1 (kích hoạt), đổi thành 0 (tắt)
        } else {
            $pair->status = true; // Nếu đang ở trạng thái 0 (tắt), đổi thành 1 (kích hoạt)
        }

        // Lưu lại thay đổi
        $pair->save();

        // Trả về thông báo thành công dưới dạng JSON
        $notify = ['success' => 'Status Change Successfully'];

        return response()->json($notify); // Trả về thông báo thành công cho client
    }
}
