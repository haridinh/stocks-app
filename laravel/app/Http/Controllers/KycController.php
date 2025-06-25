<?php

namespace App\Http\Controllers; // Định nghĩa namespace của controller, giúp phân loại mã nguồn

// Import các lớp cần thiết
use App\Helpers\Helper\Helper; // Lớp Helper giúp xử lý các công việc hỗ trợ, như lưu file, lấy theme, v.v.
use App\Models\Admin; // Lớp Admin để tương tác với bảng quản trị viên
use App\Models\Configuration; // Lớp Configuration để lấy cài đặt hệ thống, ví dụ: cài đặt KYC
use App\Models\GeneralSetting; // Lớp GeneralSetting để truy vấn các thiết lập chung
use App\Notifications\KycUpdateNotification; // Lớp KycUpdateNotification để gửi thông báo khi người dùng gửi thông tin KYC
use Illuminate\Http\Request; // Lớp Request để xử lý các yêu cầu HTTP từ người dùng

class KycController extends Controller // Controller này xử lý các yêu cầu liên quan đến KYC (Kiểm tra danh tính)
{

    // Phương thức kyc: Hiển thị trang KYC nếu người dùng chưa được xác minh
    public function kyc()
    {
        // Kiểm tra nếu người dùng đã được xác minh KYC, chuyển hướng đến trang dashboard của người dùng
        if (auth()->user()->kyc == 1) {
            return redirect()->route('user.dashboard')->with('success', 'Your Kyc Verification Successfull');
        }
        
        // Đặt tiêu đề cho trang KYC
        $data['title'] = 'Kyc Verification';
        
        // Trả về view KYC cho người dùng, sử dụng theme hiện tại
        return view(Helper::theme(). 'user.kyc')->with($data);
    }


    // Phương thức kycUpdate: Xử lý việc gửi thông tin KYC của người dùng
    public function kycUpdate(Request $request)
    {
        // Lấy cài đặt chung từ bảng Configuration
        $general = Configuration::first();

        // Lấy thông tin người dùng đã đăng nhập
        $user = auth()->user();

        // Kiểm tra nếu người dùng đã gửi thông tin KYC, nếu rồi thì không cho phép gửi lại
        if ($user->kyc == 2) {
            return redirect()->back()->with('error', 'You have already submitted KYC form');
        }

        // Mảng chứa các quy tắc xác thực dữ liệu KYC
        $validation = [];
        
        // Kiểm tra nếu có cài đặt KYC trong bảng Configuration
        if ($general->kyc != null) {
            // Duyệt qua các tham số KYC từ cài đặt
            foreach ($general->kyc as $params) {
                // Xử lý các trường kiểu text hoặc textarea
                if ($params['type'] == 'text' || $params['type'] == 'textarea') {
                    // Tạo key từ tên trường, chuyển thành chữ thường và thay khoảng trắng bằng dấu gạch dưới
                    $key = strtolower(str_replace(' ', '_', $params['field_name']));

                    // Đặt quy tắc xác thực cho trường là "required" hoặc "sometimes"
                    $validationRules = $params['validation'] == 'required' ? 'required' : 'sometimes';

                    // Thêm quy tắc xác thực vào mảng
                    $validation[$key] = $validationRules;
                } else { // Xử lý các trường kiểu file (hình ảnh)
                    $key = strtolower(str_replace(' ', '_', $params['field_name']));

                    // Quy tắc xác thực cho file là "required", "image", và các định dạng ảnh hỗ trợ
                    $validationRules = ($params['validation'] == 'required' ? 'required' : 'sometimes') . "|image|mimes:jpg,png,jpeg|max:2048";

                    // Thêm quy tắc xác thực vào mảng
                    $validation[$key] = $validationRules;
                }
            }
        }

        // Xác thực dữ liệu gửi lên từ người dùng theo các quy tắc đã định nghĩa
        $data = $request->validate($validation);

        // Duyệt qua các trường dữ liệu đã xác thực
        foreach ($data as $key => $upload) {

            // Nếu có file được tải lên
            if ($request->hasFile($key)) {

                // Lưu file và lấy tên file đã được lưu
                $filename = Helper::saveImage($upload, Helper::filePath('user'));

                // Lưu lại thông tin file đã lưu
                $data[$key] = ['file' => $filename, 'type' => 'file'];
            }
        }

        // Lưu thông tin KYC vào bảng người dùng
        $user->kyc_information = $data;

        // Cập nhật trạng thái KYC của người dùng thành "Đã xác minh"
        $user->is_kyc_verified = 2;

        // Lưu lại thông tin người dùng
        $user->save();

        // Lấy thông tin quản trị viên (loại "super" là quản trị viên chính)
        $admin = Admin::where('type','super')->first();

        // Gửi thông báo đến quản trị viên về việc người dùng đã gửi thông tin KYC
        $admin->notify(new KycUpdateNotification($user));

        // Quay lại trang trước và thông báo gửi KYC thành công
        return back()->with('success', 'Successfully send Kyc Information to Admin');
    }
}
