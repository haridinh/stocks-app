<?php

namespace App\Http\Controllers\Backend; // Định nghĩa namespace cho controller, giúp phân loại mã nguồn

// Import các lớp và helper cần thiết
use App\Helpers\Helper\Helper; // Lớp Helper chứa các hàm tiện ích, ví dụ: phân trang
use App\Http\Controllers\Controller; // Lớp Controller cơ bản của Laravel
use App\Models\CurrencyPair; // Lớp CurrencyPair, sử dụng để làm việc với các cặp tiền tệ
use Illuminate\Http\Request; // Lớp Request của Laravel để nhận dữ liệu từ client

class SignalCurrencyPairController extends Controller
{
    // Hàm hiển thị trang quản lý các cặp tiền tệ
    public function index(Request $request)
    {
        // Đặt tiêu đề trang
        $data['title'] = 'Manage Currency pair';

        // Lấy danh sách các cặp tiền tệ theo tìm kiếm và phân trang
        $data['pairs'] = CurrencyPair::search($request->search) // Tìm kiếm các cặp tiền tệ theo từ khóa
            ->latest() // Sắp xếp theo thứ tự mới nhất
            ->paginate(Helper::pagination()); // Phân trang, sử dụng hàm pagination từ Helper

        // Trả về view 'backend.currency_pair.index' với dữ liệu đã xử lý
        return view('backend.currency_pair.index')->with($data);
    }

    // Hàm xử lý việc tạo một cặp tiền tệ mới
    public function store(Request $request)
    {
        // Xác thực dữ liệu nhận được từ request
        $data = $request->validate([
            'name' => 'required|max:255|unique:currency_pairs,name', // Tên cặp tiền tệ phải có và không trùng lặp trong bảng currency_pairs
            'status' => 'required|in:0,1' // Trạng thái phải là 0 (không hoạt động) hoặc 1 (hoạt động)
        ]);

        // Tạo một cặp tiền tệ mới với dữ liệu đã xác thực
        CurrencyPair::create($data);

        // Quay lại trang trước đó với thông báo thành công
        return redirect()->back()->with('success', 'Currency pair created successfully');
    }

    // Hàm xử lý việc cập nhật cặp tiền tệ
    public function update(Request $request, $id)
    {
        // Lấy cặp tiền tệ theo ID
        $pair = CurrencyPair::findOrFail($id);

        // Xác thực dữ liệu nhận được từ request
        $data = $request->validate([
            'name' => 'required|max:255|unique:currency_pairs,name,' . $pair->id, // Tên cặp tiền tệ phải có và không trùng lặp (ngoại trừ cặp tiền tệ hiện tại)
            'status' => 'required|in:0,1' // Trạng thái phải là 0 hoặc 1
        ]);

        // Cập nhật cặp tiền tệ với dữ liệu đã xác thực
        $pair->update($data);

        // Quay lại trang trước đó với thông báo thành công
        return redirect()->back()->with('success', 'Currency pair updated successfully');
    }

    // Hàm xử lý việc xóa một cặp tiền tệ
    public function destroy($id)
    {
        // Lấy cặp tiền tệ theo ID
        $pair = CurrencyPair::findOrFail($id);
        
        // Xóa cặp tiền tệ
        $pair->delete();

        // Quay lại trang trước đó với thông báo thành công
        return redirect()->back()->with('success', 'Currency pair Deleted successfully');
    }

    // Hàm thay đổi trạng thái của cặp tiền tệ
    public function changeStatus($id)
    {
        // Lấy cặp tiền tệ theo ID
        $pair = CurrencyPair::findOrFail($id);

        // Nếu trạng thái hiện tại là 1 (hoạt động), đổi thành 0 (không hoạt động), ngược lại
        if ($pair->status) {
            $pair->status = false; // Đặt trạng thái là không hoạt động
        } else {
            $pair->status = true; // Đặt trạng thái là hoạt động
        }

        // Lưu lại thay đổi trạng thái
        $pair->save();

        // Trả về phản hồi JSON với thông báo thay đổi trạng thái thành công
        $notify = ['success' => 'Status Change Successfully'];

        return response()->json($notify);
    }
}
