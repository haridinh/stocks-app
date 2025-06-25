<?php

namespace App\Http\Controllers;

use App\Helpers\Helper\Helper;                 // Lớp Helper chứa các phương thức chung như lấy theme, file path, v.v.
use App\Http\Requests\UserProfile;             // Lớp yêu cầu xác thực khi người dùng cập nhật thông tin profile
use App\Models\User;                           // Mô hình dữ liệu cho User (người dùng)
use App\Services\UserDashboardService;         // Dịch vụ xử lý các tác vụ liên quan đến dashboard của người dùng
use App\Services\UserProfileService;           // Dịch vụ xử lý các tác vụ liên quan đến thông tin profile người dùng
use Illuminate\Http\Request;                   // Lớp Request của Laravel để xử lý các yêu cầu HTTP
use Illuminate\Support\Facades\Hash;            // Lớp Hash của Laravel để mã hóa và xác thực mật khẩu

class UserController extends Controller
{
    protected $profile, $dashboard; // Khai báo các thuộc tính lưu dịch vụ Profile và Dashboard

    // Hàm khởi tạo, nhận các dịch vụ UserProfileService và UserDashboardService
    public function __construct(UserProfileService $profile, UserDashboardService $dashboard)
    {
        $this->profile = $profile;  // Gán đối tượng UserProfileService vào thuộc tính $profile
        $this->dashboard = $dashboard;  // Gán đối tượng UserDashboardService vào thuộc tính $dashboard
    }

    // Hàm hiển thị trang dashboard của người dùng
    public function dashboard()
    {
        // Lấy dữ liệu cho dashboard từ dịch vụ UserDashboardService
        $data = $this->dashboard->dashboard();

        // Cấu hình tiêu đề cho trang
        $data['title'] = "Dashboard";

        // Trả về view dashboard với dữ liệu được lấy
        return view(Helper::theme() . 'user.dashboard')->with($data);
    }

    // Hàm hiển thị trang chỉnh sửa thông tin cá nhân (profile)
    public function profile()
    {
        // Cấu hình tiêu đề cho trang
        $data['title'] = 'Profile Edit';

        // Lấy thông tin người dùng hiện tại
        $data['user'] = auth()->user();

        // Trả về view chỉnh sửa profile với dữ liệu người dùng
        return view(Helper::theme() . 'user.profile')->with($data);
    }

    // Hàm xử lý việc cập nhật thông tin cá nhân (profile)
    public function profileUpdate(UserProfile $request)
    {
        // Gọi dịch vụ UserProfileService để cập nhật thông tin người dùng
        $isSuccess = $this->profile->update($request);

        // Nếu cập nhật thành công, quay lại trang trước với thông báo thành công
        if ($isSuccess['type'] === 'success')
            return back()->with('success', $isSuccess['message']);
    }

    // Hàm hiển thị trang thay đổi mật khẩu
    public function changePassword()
    {
        // Cấu hình tiêu đề cho trang thay đổi mật khẩu
        $title = 'Change Password';

        // Trả về view thay đổi mật khẩu với tiêu đề trang
        return view(Helper::theme() . 'user.changepassword', compact('title'));
    }

    // Hàm xử lý việc cập nhật mật khẩu người dùng
    public function updatePassword(Request $request)
    {
        // Xác thực dữ liệu đầu vào
        $request->validate([
            'oldpassword' => 'required|min:6',  // Mật khẩu cũ phải có ít nhất 6 ký tự
            'password' => 'min:6|confirmed',    // Mật khẩu mới phải có ít nhất 6 ký tự và phải xác nhận đúng
        ]);

        // Lấy thông tin người dùng hiện tại từ cơ sở dữ liệu
        $user = User::find(auth()->id());

        // Kiểm tra mật khẩu cũ có đúng không
        if (!Hash::check($request->oldpassword, $user->password)) {
            // Nếu mật khẩu cũ không đúng, quay lại trang trước với thông báo lỗi
            return redirect()->back()->with('error', 'Old password do not match');
        } else {
            // Nếu mật khẩu cũ đúng, cập nhật mật khẩu mới
            $user->password = bcrypt($request->password); // Mã hóa mật khẩu mới

            // Lưu thay đổi vào cơ sở dữ liệu
            $user->save();

            // Quay lại trang trước với thông báo thành công
            return redirect()->back()->with('success', 'Password Updated');
        }
    }
}
