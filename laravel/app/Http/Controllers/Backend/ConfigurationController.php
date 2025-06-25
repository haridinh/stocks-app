<?php

namespace App\Http\Controllers\Backend; // Đặt namespace cho controller này vào nhóm Backend

use App\Helpers\Helper\Helper; // Sử dụng Helper từ thư mục Helpers
use App\Http\Controllers\Controller; // Kế thừa controller cơ bản của Laravel
use App\Http\Requests\ConfigurationRequest; // Sử dụng lớp request tùy chỉnh để xử lý xác thực thông tin đầu vào cho cấu hình
use App\Models\Configuration; // Sử dụng model Configuration để làm việc với bảng cấu hình
use App\Services\ConfigurationService; // Sử dụng dịch vụ ConfigurationService để xử lý logic nghiệp vụ
use Illuminate\Http\Request; // Lớp Request của Laravel để xử lý các yêu cầu HTTP
use Illuminate\Support\Facades\Artisan; // Sử dụng facade Artisan để gọi các lệnh Artisan trong Laravel

class ConfigurationController extends Controller // Khai báo lớp ConfigurationController để xử lý các yêu cầu liên quan đến cấu hình hệ thống
{
    protected $config; // Khai báo thuộc tính config để lưu đối tượng ConfigurationService

    // Khởi tạo controller với ConfigurationService
    public function __construct(ConfigurationService $config)
    {
        $this->config = $config; // Gán dịch vụ ConfigurationService vào thuộc tính config
    }

    // Hàm hiển thị trang cấu hình chung của ứng dụng
    public function index()
    {
        $data['title'] = 'Application Settings'; // Tiêu đề trang

        $data['general'] = Configuration::first(); // Lấy bản ghi cấu hình chung đầu tiên trong bảng Configuration

        // Lấy thông tin múi giờ từ file JSON trong thư mục backend/setting
        $data['timezone'] = json_decode(file_get_contents(resource_path('views/backend/setting/timezone.json')));

        // Trả về view cấu hình với dữ liệu đã chuẩn bị
        return view('backend.setting.index')->with($data);
    }

    // Hàm xử lý cập nhật cấu hình chung của ứng dụng
    public function ConfigurationUpdate(ConfigurationRequest $request)
    {
        // Gọi phương thức general từ ConfigurationService để xử lý cập nhật cấu hình
        $isSuccess = $this->config->general($request);

        // Kiểm tra kết quả cập nhật và trả về thông báo thành công nếu thành công
        if ($isSuccess['type'] == 'success')
            return back()->with('success', $isSuccess['message']);
    }

    // Hàm xử lý xóa cache của ứng dụng
    public function cacheClear()
    {
        // Gọi các lệnh Artisan để xóa cache và tối ưu cấu hình
        Artisan::call('cache:clear');
        Artisan::call('config:clear');
        Artisan::call('optimize:clear');

        // Trả về thông báo thành công sau khi xóa cache
        return back()->with('success', 'Caches cleared successfully!');
    }

    // Hàm hiển thị trang quản lý theme (giao diện)
    public function manageTheme()
    {
        $data['title'] = 'Manage Theme'; // Tiêu đề trang quản lý theme
        return view('backend.setting.theme')->with($data); // Trả về view quản lý theme
    }

    // Hàm cập nhật theme và màu sắc của ứng dụng
    public function themeUpdate(Request $request)
    {
        $general = Configuration::first(); // Lấy bản ghi cấu hình chung đầu tiên

        // Cập nhật thông tin theme và màu sắc
        $general->theme = $request->name;
        $general->color = $request->color;

        $general->save(); // Lưu các thay đổi vào cơ sở dữ liệu

        // Trả về thông báo thành công sau khi cập nhật theme
        return redirect()->back()->with('success', 'Template Actived successfully');
    }

    // Hàm cập nhật theme và màu sắc thông qua AJAX
    public function themeColor(Request $request)
    {
        $general = Configuration::first(); // Lấy bản ghi cấu hình chung đầu tiên

        // Cập nhật thông tin theme và màu sắc
        $general->theme = $request->theme;
        $general->color = $request->color;

        $general->save(); // Lưu các thay đổi vào cơ sở dữ liệu

        // Trả về phản hồi JSON thành công
        return response()->json(['success' => true]);
    }
}
