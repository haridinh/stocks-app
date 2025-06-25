<?php

namespace App\Http\Controllers\Backend; // Đặt namespace cho controller này vào nhóm Backend

use App\Http\Controllers\Controller; // Kế thừa controller cơ bản của Laravel
use App\Http\Requests\AdminProfileRequest; // Sử dụng lớp request tùy chỉnh để xử lý xác thực thông tin đầu vào
use App\Services\AdminProfileService; // Sử dụng dịch vụ để xử lý logic nghiệp vụ liên quan đến quản lý hồ sơ admin
use Illuminate\Http\Request; // Lớp Request của Laravel để xử lý các yêu cầu HTTP
use Illuminate\Support\Facades\Hash; // Sử dụng facade Hash của Laravel để mã hóa và so sánh mật khẩu

class AdminProfileController extends Controller // Khai báo lớp AdminProfileController để xử lý các yêu cầu liên quan đến hồ sơ admin
{

    protected $profile; // Khai báo thuộc tính profile để lưu đối tượng AdminProfileService

    // Khởi tạo controller với AdminProfileService
    public function __construct(AdminProfileService $profile)
    {
        $this->profile = $profile; // Gán dịch vụ AdminProfileService vào thuộc tính profile
    }

    // Hàm hiển thị trang cấu hình hồ sơ của admin
    public function profile()
    {
        $data['title'] = 'Profile Settings'; // Tiêu đề của trang cấu hình hồ sơ

        return view('backend.profile')->with($data); // Trả về view với tiêu đề trang
    }

    // Hàm xử lý cập nhật thông tin hồ sơ của admin
    public function profileUpdate(AdminProfileRequest $request)
    {
        // Gọi phương thức update từ AdminProfileService để cập nhật thông tin admin
        $isSuccess = $this->profile->update($request);

        // Kiểm tra kết quả cập nhật, nếu thành công, trả về thông báo thành công
        if ($isSuccess['type'] === 'success')
            return redirect()->back()->with('success', $isSuccess['message']);
    }

    // Hàm thay đổi mật khẩu của admin
    public function changePassword(Request $request)
    {
        // Lưu thông tin loại thay đổi trong session (để nhận biết yêu cầu thay đổi mật khẩu)
        session()->put('type', 'password');

        // Xác thực yêu cầu đầu vào của người dùng
        $request->validate([
            'old_password' => 'required', // Mật khẩu cũ là bắt buộc
            'password' => 'required|min:6|confirmed' // Mật khẩu mới phải có ít nhất 6 ký tự và phải khớp với xác nhận
        ]);

        // Lấy thông tin admin hiện tại
        $admin = auth()->guard('admin')->user();

        // Kiểm tra xem mật khẩu cũ có đúng không
        if (!Hash::check($request->old_password, $admin->password)) {
            // Nếu mật khẩu cũ không đúng, trả về thông báo lỗi
            return back()->with('error', 'Password Does not match');
        }

        // Mã hóa mật khẩu mới và lưu vào cơ sở dữ liệu
        $admin->password = bcrypt($request->password);
        $admin->save();

        // Trả về thông báo thành công sau khi cập nhật mật khẩu
        return back()->with('success', 'Password changed Successfully');
    }
}
