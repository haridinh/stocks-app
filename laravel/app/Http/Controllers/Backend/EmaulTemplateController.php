<?php

namespace App\Http\Controllers\Backend; // Đặt namespace cho controller này vào nhóm Backend

use App\Helpers\Helper\Helper; // Sử dụng Helper từ thư mục Helpers để làm việc với các chức năng tiện ích
use App\Http\Controllers\Controller; // Kế thừa controller cơ bản của Laravel
use App\Models\Configuration; // Sử dụng model Configuration để làm việc với bảng cấu hình
use App\Models\EmailTemplate; // Sử dụng model EmailTemplate (không sử dụng trong mã này, có thể để sẵn cho mở rộng sau)
use App\Models\GeneralSetting; // Sử dụng model GeneralSetting (không sử dụng trong mã này, có thể để sẵn cho mở rộng sau)
use App\Models\Template; // Sử dụng model Template để làm việc với bảng Email Templates
use Illuminate\Http\Request; // Lớp Request của Laravel để xử lý các yêu cầu HTTP

class EmailTemplateController extends Controller // Khai báo lớp EmailTemplateController để xử lý các yêu cầu liên quan đến cấu hình email và template email
{
    // Hàm hiển thị trang cấu hình email
    public function emailConfig()
    {
        $data['title'] = 'Email Configuration'; // Tiêu đề của trang cấu hình email

        return view('backend.email.config')->with($data); // Trả về view cấu hình email với dữ liệu đã chuẩn bị
    }

    // Hàm xử lý cập nhật cấu hình email
    public function emailConfigUpdate(Request $request)
    {
        $general = Configuration::first(); // Lấy bản ghi cấu hình chung đầu tiên trong bảng Configuration

        // Xác thực các trường nhập từ yêu cầu
        $data = $request->validate([
            'email_sent_from' => 'required|email', // Kiểm tra email người gửi phải hợp lệ
            'email_config' => "required_if:email_method,==,smtp", // Kiểm tra các trường cấu hình SMTP nếu email_method là smtp
            'email_config.*' => 'required_if:email_method,==,smtp' // Kiểm tra các trường trong email_config nếu email_method là smtp
        ]);

        // Nếu chọn SMTP làm phương thức gửi email
        if($request->smtp == 'on'){
            $data = [
                'MAIL_DRIVER' => 'smtp', // Cài đặt driver cho mail là SMTP
                'MAIL_HOST' => $request->email_config['smtp_host'], // Lấy địa chỉ SMTP host
                'MAIL_PORT' => $request->email_config['smtp_port'], // Lấy cổng SMTP
                'MAIL_USERNAME' => $request->email_config['smtp_username'], // Lấy tên đăng nhập SMTP
                'MAIL_PASSWORD' => $request->email_config['smtp_password'], // Lấy mật khẩu SMTP
                'MAIL_ENCRYPTION' => $request->email_config['smtp_encryption'], // Lấy phương thức mã hóa SMTP
                'MAIL_FROM_ADDRESS' =>  $request->email_sent_from // Địa chỉ email người gửi
            ];
        } else { // Nếu chọn phương thức mail PHP
            $data = [
                'MAIL_DRIVER' => 'mail', // Cài đặt driver cho mail là PHP mail
                'MAIL_HOST' =>'',
                'MAIL_PORT' =>'',
                'MAIL_USERNAME' =>'',
                'MAIL_PASSWORD' =>'',
                'MAIL_ENCRYPTION' =>'',
                'MAIL_FROM_ADDRESS' =>  $request->email_sent_from // Địa chỉ email người gửi
            ];
        }

        // Cập nhật thông tin cấu hình vào bảng Configuration
        $general->email_method = $request->smtp === 'on' ? 'smtp' : 'php'; // Lưu phương thức gửi email
        $general->email_sent_from = $request->email_sent_from; // Lưu địa chỉ email người gửi
        if($request->smtp === 'on'){ // Nếu dùng SMTP, lưu cấu hình SMTP
            $general->email_config = $data;
        }

        $general->save(); // Lưu thay đổi vào cơ sở dữ liệu

        // Cập nhật các giá trị trong file .env thông qua helper setEnv
        Helper::setEnv($data);

        // Trả về thông báo thành công và quay lại trang trước
        return redirect()->back()->with('success', "Email Setting Updated Successfully");
    }

    // Hàm hiển thị danh sách các email templates
    public function emailTemplates()
    {
        $data['title'] = 'Email Templates'; // Tiêu đề của trang email templates

        // Lấy tất cả các email templates, phân trang theo cấu hình pagination
        $data['emailTemplates'] = Template::latest()->paginate(Helper::pagination());

        return view('backend.email.templates')->with($data); // Trả về view với danh sách templates
    }

    // Hàm hiển thị form chỉnh sửa một email template
    public function emailTemplatesEdit(Template $template)
    {
        $title = 'Template Edit'; // Tiêu đề của trang chỉnh sửa template

        return view('backend.email.edit', compact('title', 'template')); // Trả về view chỉnh sửa template với dữ liệu đã chuẩn bị
    }

    // Hàm xử lý cập nhật email template
    public function emailTemplatesUpdate(Request $request, Template $template)
    {
        // Xác thực các trường nhập từ yêu cầu
        $data = $request->validate([
            'subject' => 'required', // Kiểm tra trường subject là bắt buộc
            'template' => 'required' // Kiểm tra trường template là bắt buộc
        ]);

        // Lưu trạng thái của template (bật/tắt)
        $data['status'] = $request->status === 'on' ? true : false;

        // Cập nhật template với các giá trị mới
        $template->update($data);

        // Trả về thông báo thành công và quay lại trang trước
        return redirect()->back()->with('success', "Email Template Updated Successfully");
    }
}
