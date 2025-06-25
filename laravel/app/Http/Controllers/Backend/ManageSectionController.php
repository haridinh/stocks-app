<?php

namespace App\Http\Controllers\Backend; // Đặt namespace cho controller này vào nhóm Backend

use App\Helpers\Helper\Helper; // Sử dụng Helper từ thư mục Helpers để làm việc với các chức năng tiện ích
use App\Http\Controllers\Controller; // Kế thừa controller cơ bản của Laravel
use App\Http\Requests\SectionElementRequest; // Sử dụng request để xác thực các dữ liệu đầu vào cho việc tạo phần tử trong section
use App\Http\Requests\SectionElementUpdateRequest; // Xác thực yêu cầu cập nhật phần tử của section
use App\Http\Requests\SectionRequest; // Xác thực yêu cầu cho việc cập nhật các section
use App\Models\Content; // Mô hình liên quan đến nội dung các phần tử trong website
use App\Models\GeneralSetting; // Quản lý các cài đặt chung của ứng dụng
use App\Models\Language; // Quản lý thông tin ngôn ngữ của các phần tử
use App\Models\SectionData; // Quản lý thông tin các section (mục, phần)
use App\Services\SectionManagerService; // Dịch vụ xử lý các thao tác với các section
use App\Utility\Config; // Tiện ích cấu hình cho ứng dụng
use App\Utility\ElementBuilder; // Tiện ích xây dựng phần tử giao diện
use App\Utility\FormBuilder; // Tiện ích xây dựng form cho các phần tử
use Illuminate\Http\Request; // Lớp Request của Laravel để xử lý các yêu cầu HTTP
use Illuminate\Support\Str; // Hỗ trợ xử lý chuỗi

class ManageSectionController extends Controller // Controller xử lý các thao tác liên quan đến các section trong backend
{

    protected $content; // Thuộc tính để lưu dịch vụ quản lý nội dung (SectionManagerService)

    function __construct(SectionManagerService $content) // Khởi tạo controller và inject dịch vụ SectionManagerService
    {
        $this->content = $content;
    }

    // Hiển thị và quản lý một section cụ thể (section được yêu cầu trong request)
    public function section(Request $request)
    {

        // Lấy các section từ cấu hình
        $data['sections'] = Config::sections();

        // Lấy danh sách các phần tử của một section cụ thể
        $data['elements'] = Content::where('theme', Helper::config()->theme)
                                   ->where('type', 'iteratable')
                                   ->where('name', $request->name)
                                   ->get();
    
        // Xây dựng form tương ứng với tên section
        $elementBuilder = FormBuilder::classMap($request->name);

        // Kiểm tra xem form có phần tử nào không
        if ($elementBuilder->has_element) {

            // Lọc các trường có thể là 'Text' hoặc 'Upload'
            $data['elementsHeader'] = array_filter(FormBuilder::classMap($request->name)->elementFields[Helper::config()->theme], function ($item) {
                return $item == 'Text' || $item == 'Upload';
            });
        }

        // Tiêu đề trang
        $data['title'] = "Manage {$request->name} Section";

        // Trả về view để quản lý section
        return view('backend.frontend.index')->with($data);
    }

    // Cập nhật nội dung của một section
    public function sectionContentUpdate(SectionRequest $request)
    {

        // Lấy tất cả dữ liệu trừ token
        $data = $request->except('_token');

        // Gọi dịch vụ để cập nhật nội dung của section
        $isSuccess = $this->content->contentUpdate($request);

        // Kiểm tra kết quả trả về từ dịch vụ và thông báo cho người dùng
        if ($isSuccess['type'] === 'success') {

            return redirect()->back()->with('success', $isSuccess['message']);
        }
    }

    // Hiển thị form tạo mới phần tử trong một section
    public function sectionElement(Request $request)
    {
        // Tiêu đề trang tạo phần tử
        $data['title'] = ucwords($request->name) . " Element";

        // Trả về view tạo phần tử cho section
        return view('backend.frontend.element')->with($data);
    }

    // Tạo phần tử cho một section
    public function sectionElementCreate(SectionElementRequest $request)
    {
        // Lấy tất cả dữ liệu trừ token
        $data = $request->except('_token');

        // Gọi dịch vụ để tạo phần tử mới trong section
        $isSuccess = $this->content->elementCreate($request);

        // Kiểm tra kết quả và thông báo cho người dùng
        if ($isSuccess['type'] === 'success') {

            return redirect()->back()->with('success', $isSuccess['message']);
        }
    }

    // Chỉnh sửa một phần tử của section
    public function editElement($name, Content $element)
    {
        // Tiêu đề trang chỉnh sửa phần tử
        $data['title'] = ucwords(request()->name) . " Element";

        // Truyền phần tử cần chỉnh sửa vào view
        $data['element'] = $element;

        // Trả về view chỉnh sửa phần tử
        return view('backend.frontend.edit')->with($data);
    }

    // Dịch một phần tử của section (cho đa ngôn ngữ)
    public function translate($name, Content $element)
    {
        // Tiêu đề trang dịch phần tử
        $data['title'] = ucwords(request()->name) . " Element";

        // Lấy ID của phần tử và danh sách các ngôn ngữ đã kích hoạt
        $data['elementId'] = $element->id;
        $data['childs'] = $element->child;  // Các phần tử con của phần tử này
        $data['languages'] = Language::where('status', 1)->get();  // Lấy các ngôn ngữ đã được kích hoạt

        // Trả về view dịch phần tử
        return view('backend.frontend.trans')->with($data);
    }

    // Cập nhật một phần tử của section
    public function updateElement(SectionElementUpdateRequest $request)
    {
        // Lấy nội dung của phần tử từ cơ sở dữ liệu
        $content = Content::where('theme', Helper::config()->theme)->find($request->element);

        // Gọi dịch vụ để cập nhật phần tử
        $isSuccess = $this->content->elementUpdate($request, $content);

        // Kiểm tra kết quả trả về và thông báo cho người dùng
        if ($isSuccess['type'] === 'error') {
            return redirect()->back()->with('error', $isSuccess['message']);
        }

        return redirect()->back()->with('success', $isSuccess['message']);
    }

    // Xóa một phần tử trong section
    public function deleteElement($name, Content $element)
    {
        // Gọi dịch vụ để xóa phần tử
        $isSuccess = $this->content->deleteElement($element);

        // Kiểm tra kết quả trả về
        if ($isSuccess['type'] === 'error') {
            // Nếu có lỗi sẽ không làm gì và vẫn quay lại trang cũ
        }

        // Thông báo đã xóa thành công phần tử
        return redirect()->back()->with('success', "{$name} Deleted Successfully");
    }
}
