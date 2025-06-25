<?php

// Khai báo namespace cho controller, đặt trong thư mục Backend của ứng dụng
namespace App\Http\Controllers\Backend;

// Nhập các lớp và helper cần thiết
use App\Helpers\Helper\Helper; // Helper cung cấp các hàm tiện ích (phân trang, cấu hình, v.v.)
use App\Http\Controllers\Controller; // Class cơ sở cho controller
use App\Http\Requests\LanguageRequest; // Request tùy chỉnh để xác thực dữ liệu khi tạo ngôn ngữ
use App\Models\Content;
use App\Models\Language; // Model đại diện cho bảng languages
use App\Services\LanguageService; // Service xử lý logic liên quan đến ngôn ngữ
use App\Utility\ElementBuilder;
use App\Utility\FormBuilder; // Form Builder
use Illuminate\Http\Request; // Class xử lý request HTTP
use Illuminate\Support\Facades\App; // Facade để quản lý locale của ứng dụng


// Class LanguageController kế thừa từ Controller
class LanguageController extends Controller
{
    // Khai báo thuộc tính để lưu trữ LanguageService
    protected $language;

    // Constructor: Tiêm LanguageService vào controller
    public function __construct(LanguageService $language)
    {
        $this->language = $language;
    }

    // Phương thức index: Hiển thị danh sách ngôn ngữ
    public function index()
    {
        // Đặt tiêu đề trang
        $data['title'] = "Language Settings";

        // Lấy danh sách ngôn ngữ với phân trang
        $data['languages'] = Language::latest()->paginate(Helper::pagination());

        // Trả về view danh sách ngôn ngữ
        return view('backend.language.index')->with($data);
    }

    // Phương thức store: Tạo ngôn ngữ mới
    public function store(LanguageRequest $request)
    {
        // Gọi phương thức create từ LanguageService để tạo ngôn ngữ
        $isSuccess = $this->language->create($request);

        // Kiểm tra kết quả và chuyển hướng về trang trước với thông báo
        if ($isSuccess['type'] === 'success') {
            return redirect()->back()->with('success', $isSuccess['message']);
        }
    }

    // Phương thức update: Cập nhật ngôn ngữ
    public function update(Request $request)
    {
        // Gọi phương thức update từ LanguageService để cập nhật ngôn ngữ
        $isSuccess = $this->language->update($request);

        // Kiểm tra kết quả và chuyển hướng về trang trước với thông báo
        if ($isSuccess['type'] === 'error') {
            return back()->with('error', $isSuccess['message']);
        }
        return back()->with('success', $isSuccess['message']);
    }

    // Phương thức delete: Xóa ngôn ngữ
    public function delete(Request $request)
    {
        // Gọi phương thức delete từ LanguageService để xóa ngôn ngữ
        $isSuccess = $this->language->delete($request);

        // Kiểm tra kết quả và chuyển hướng về trang trước với thông báo
        if ($isSuccess['type'] === 'error') {
            return redirect()->back()->with('error', $isSuccess['message']);
        }
        return redirect()->back()->with('success', $isSuccess['message']);
    }

    // Phương thức transalate: Hiển thị giao diện dịch ngôn ngữ
    public function transalate(Request $request)
    {
        // Đặt tiêu đề trang
        $data['title'] = "Language Translator";

        // Lấy danh sách ngôn ngữ, loại trừ ngôn ngữ hiện tại
        $data['languages'] = Language::where('code', '!=', $request->lang)->get();

        // Tìm ngôn ngữ theo mã code
        $language = Language::where('code', $request->lang)->firstOrFail();

        // Đọc dữ liệu dịch từ file JSON (cho backend)
        $data['translators'] = collect(json_decode(file_get_contents(resource_path() . "/lang/$language->code.json"), true));

        // Đọc dữ liệu dịch từ file JSON (cho frontend sections)
        $data['frontendtranslators'] = collect(json_decode(file_get_contents(resource_path() . "/lang/sections/$language->code.json"), true));

        // Gán tất cả dữ liệu dịch vào biến 'all'
        $data['all'] = $data['translators'];

        // Trả về view dịch ngôn ngữ
        return view('backend.language.translate')->with($data);
    }

    // Phương thức transalateUpate: Cập nhật một cặp key-value dịch
    public function transalateUpate(Request $request)
    {
        // Xác thực dữ liệu đầu vào
        $request->validate([
            'key' => 'required', // Key bắt buộc
            'value' => 'required', // Value bắt buộc
        ]);

        // Tìm ngôn ngữ theo mã code
        $language = Language::where('code', $request->lang)->firstOrFail();

        // Đọc dữ liệu dịch hiện tại từ file JSON
        $trans = json_decode(file_get_contents(resource_path() . "/lang/$language->code.json"), true);

        // Cập nhật cặp key-value mới
        $trans[$request->key] = $request->value;

        // Ghi lại dữ liệu vào file JSON
        file_put_contents(resource_path() . "/lang/$language->code.json", json_encode($trans));

        // Chuyển hướng về trang trước
        return back();
    }

    // Phương thức ajaxUpdate: Cập nhật nhiều cặp key-value dịch qua AJAX
    public function ajaxUpdate(Request $request)
    {
        // Tìm ngôn ngữ theo mã code
        $language = Language::where('code', $request->lang)->first();

        // Kết hợp mảng key và value từ request
        $trans = array_combine($request->key, $request->value);

        // Đọc dữ liệu dịch hiện tại từ file JSON (tùy thuộc vào type: section hoặc backend)
        if ($request->type == 'section') {
            $previous = json_decode(file_get_contents(resource_path() . "/lang/sections/$language->code.json"), true);
        } else {
            $previous = json_decode(file_get_contents(resource_path() . "/lang/$language->code.json"), true);
        }

        // Kết hợp dữ liệu mới với dữ liệu cũ
        $final = $trans + $previous;

        // Ghi dữ liệu vào file JSON tương ứng
        if ($request->type == 'section') {
            file_put_contents(resource_path() . "/lang/sections/$language->code.json", json_encode($final));
        } else {
            file_put_contents(resource_path() . "/lang/$language->code.json", json_encode($final));
        }

        // Chuyển hướng về trang trước với thông báo thành công
        return redirect()->back()->with('success', 'Language Key value update successfully');
    }

    // Phương thức deleteKey: Xóa một cặp key-value dịch
    public function deleteKey(Request $request)
    {
        // Tìm ngôn ngữ theo mã code
        $language = Language::where('code', $request->lang)->first();

        // Đọc dữ liệu dịch hiện tại từ file JSON (tùy thuộc vào type: section hoặc backend)
        if ($request->type == 'section') {
            $previous = json_decode(file_get_contents(resource_path() . "/lang/sections/$language->code.json"), true);
        } else {
            $previous = json_decode(file_get_contents(resource_path() . "/lang/$language->code.json"), true);
        }

        // Xóa key được chỉ định
        unset($previous[$request->key]);

        // Ghi lại dữ liệu vào file JSON tương ứng
        if ($request->type == 'section') {
            file_put_contents(resource_path() . "/lang/sections/$language->code.json", json_encode($previous));
        } else {
            file_put_contents(resource_path() . "/lang/$language->code.json", json_encode($previous));
        }

        // Chuyển hướng về trang trước với thông báo thành công
        return redirect()->back()->with('success', 'Language Key value Deleted successfully');
    }

    // Phương thức changeLang: Thay đổi ngôn ngữ hiện tại của ứng dụng
    public function changeLang(Request $request)
    {
        // Đặt locale mới cho ứng dụng
        App::setLocale($request->lang);

        // Lưu locale vào session
        session()->put('locale', $request->lang);

        // Chuyển hướng về trang trước với thông báo thành công
        return redirect()->back()->with('success', __('Successfully Changed Language'));
    }
}