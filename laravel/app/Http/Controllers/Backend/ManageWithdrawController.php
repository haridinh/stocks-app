<?php

namespace App\Http\Controllers\Backend; // Đặt namespace cho controller này vào nhóm Backend

use App\Helpers\Helper\Helper; // Sử dụng Helper từ thư mục Helpers để làm việc với các chức năng tiện ích
use App\Http\Controllers\Controller; // Kế thừa controller cơ bản của Laravel
use App\Http\Requests\WithdrawRequest; // Sử dụng request để xác thực các dữ liệu đầu vào khi tạo hoặc cập nhật phương thức rút tiền
use App\Models\Withdraw; // Mô hình quản lý các yêu cầu rút tiền của người dùng
use App\Models\WithdrawGateway; // Mô hình quản lý các cổng rút tiền (withdraw methods)
use App\Services\WithdrawService; // Dịch vụ xử lý các thao tác liên quan đến việc rút tiền
use Illuminate\Http\Request; // Lớp Request của Laravel để xử lý các yêu cầu HTTP

class ManageWithdrawController extends Controller // Controller xử lý các thao tác liên quan đến phương thức và yêu cầu rút tiền
{
    protected $gateway; // Thuộc tính lưu dịch vụ quản lý rút tiền

    // Constructor để inject dịch vụ WithdrawService vào controller
    public function __construct(WithdrawService $gateway)
    {
        $this->gateway = $gateway; // Gán dịch vụ rút tiền vào thuộc tính
    }

    // Hiển thị danh sách các phương thức rút tiền
    public function index(Request $request)
    {
        $data['title'] = 'Withdraw Methods'; // Tiêu đề trang

        $search = $request->search; // Lấy từ khóa tìm kiếm từ request

        // Lọc các phương thức rút tiền theo tên nếu có tìm kiếm
        $data['withdraws'] = WithdrawGateway::when($search, function ($q) use ($search) {
            $q->where('name', 'LIKE', '%' . $search . '%'); // Tìm kiếm theo tên phương thức rút tiền
        })->latest()->paginate(Helper::pagination()); // Lấy các phương thức rút tiền mới nhất và phân trang

        // Trả về view danh sách phương thức rút tiền
        return view('backend.withdraw.index')->with($data);
    }

    // Tạo phương thức rút tiền mới
    public function withdrawMethodCreate(WithdrawRequest $request)
    {
        // Gọi dịch vụ để tạo phương thức rút tiền mới
        $isSuccess = $this->gateway->create($request);

        // Kiểm tra kết quả và trả về thông báo
        if ($isSuccess['type'] == 'success')
            return redirect()->back()->with('success', $isSuccess['message']); // Thông báo thành công
    }

    // Cập nhật phương thức rút tiền
    public function withdrawMethodUpdate(WithdrawRequest $request)
    {
        // Gọi dịch vụ để cập nhật phương thức rút tiền
        $isSuccess = $this->gateway->update($request);

        // Kiểm tra kết quả và trả về thông báo
        if ($isSuccess['type'] == 'success')
            return redirect()->back()->with('success', $isSuccess['message']); // Thông báo thành công
    }

    // Xóa phương thức rút tiền
    public function withdrawMethodDelete(Request $request)
    {
        // Gọi dịch vụ để xóa phương thức rút tiền
        $isSuccess = $this->gateway->delete($request);

        // Kiểm tra kết quả và trả về thông báo
        if ($isSuccess['type'] == 'error') {
            return redirect()->back()->with('error', $isSuccess['message']); // Thông báo lỗi
        }

        return redirect()->back()->with('success', $isSuccess['message']); // Thông báo thành công
    }

    // Lọc các yêu cầu rút tiền
    public function filterWithdraw(Request $request)
    {
        // Lọc yêu cầu rút tiền theo các tiêu chí từ dịch vụ
        $data = $this->gateway->filter($request);

        $data['title'] = 'Withdraw Logs'; // Tiêu đề trang

        // Nếu là yêu cầu AJAX, trả về view AJAX với dữ liệu đã lọc
        if ($data['is_ajax']) {
            return view('backend.withdraw.withdraw_ajax')->with($data);
        }

        // Nếu không phải AJAX, trả về view đầy đủ
        return view('backend.withdraw.withdraw_all')->with($data);
    }

    // Chấp nhận yêu cầu rút tiền
    public function withdrawAccept(Withdraw $withdraw)
    {
        // Gọi dịch vụ để chấp nhận yêu cầu rút tiền
        $isSuccess = $this->gateway->accept($withdraw);

        // Kiểm tra kết quả và trả về thông báo
        if ($isSuccess['type'] === 'success')
            return redirect()->back()->with('success', 'Withdraw Accepted Successfully'); // Thông báo thành công
    }

    // Từ chối yêu cầu rút tiền và yêu cầu lý do từ người quản trị
    public function withdrawReject(Request $request, Withdraw $withdraw)
    {
        // Xác thực lý do từ chối yêu cầu rút tiền
        $request->validate(['reason_of_reject' => 'required']);

        // Gọi dịch vụ để từ chối yêu cầu rút tiền
        $isSuccess = $this->gateway->reject($withdraw, $request);

        // Kiểm tra kết quả và trả về thông báo
        if ($isSuccess['type'] === 'success')
            return redirect()->back()->with('success', $isSuccess['message']); // Thông báo thành công
    }

    // Hiển thị chi tiết yêu cầu rút tiền
    public function withdrawLog(Request $request, $id)
    {
        // Gọi dịch vụ để lấy log yêu cầu rút tiền theo id
        $isSuccess = $this->gateway->log($request, $id);

        // Trả về view chi tiết yêu cầu rút tiền với dữ liệu đã lấy
        return view('backend.withdraw.details')->with($isSuccess['data']);
    }
}
