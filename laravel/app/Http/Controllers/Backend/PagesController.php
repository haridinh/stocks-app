<?php

namespace App\Http\Controllers\Backend; // Đặt namespace cho controller này vào nhóm Backend

use App\Helpers\Helper\Helper; // Sử dụng Helper từ thư mục Helpers để hỗ trợ các chức năng tiện ích
use App\Http\Controllers\Controller; // Kế thừa controller cơ bản của Laravel
use App\Http\Requests\PagesRequest; // Yêu cầu xác thực dữ liệu đầu vào cho việc tạo và cập nhật trang
use App\Models\Page; // Mô hình quản lý các trang
use App\Services\PageService; // Dịch vụ quản lý các thao tác với trang (Page)
use Illuminate\Http\Request; // Lớp Request của Laravel để xử lý các yêu cầu HTTP

class PagesController extends Controller // Controller xử lý các thao tác liên quan đến trang
{
    protected $page; // Khai báo thuộc tính để lưu đối tượng dịch vụ PageService

    // Hàm khởi tạo, inject PageService vào controller
    function __construct(PageService $page)
    {
        $this->page = $page; // Gán dịch vụ PageService cho thuộc tính $page
    }

    // Hiển thị danh sách các trang
    public function index(Request $request)
    {
        $data['title'] = 'Manage Pages'; // Tiêu đề trang

        // Lấy danh sách các trang, có tính năng tìm kiếm theo tên và phân trang
        $data['pages'] = Page::when($request->search, function ($query) use ($request) {
            // Nếu có từ khóa tìm kiếm, áp dụng điều kiện WHERE để tìm các trang có tên chứa từ khóa
            $query->where('name', 'LIKE', '%' . $request->search . '%');
        })->orderBy('id', 'ASC') // Sắp xếp theo ID tăng dần
          ->paginate(Helper::pagination()); // Phân trang với số lượng tối đa được cấu hình trong Helper

        // Trả về view để hiển thị danh sách trang
        return view('backend.page.index')->with($data);
    }

    // Hiển thị form tạo mới trang
    public function pageCreate()
    {
        $title = 'Create Page'; // Tiêu đề trang

        // Trả về view tạo mới trang với tiêu đề
        return view('backend.page.create', compact('title'));
    }

    // Xử lý việc tạo mới trang
    public function pageInsert(PagesRequest $request)
    {
        // Sử dụng dịch vụ PageService để tạo mới trang
        $isSuccess = $this->page->create($request);

        // Nếu tạo trang thành công, chuyển hướng tới trang quản lý các trang với thông báo thành công
        if ($isSuccess['type'] === 'success') {
            return redirect()->route('admin.frontend.pages')->with('success', $isSuccess['message']);
        }
    }

    // Hiển thị form chỉnh sửa trang
    public function pageEdit(Request $request)
    {
        $title = "Edit Page"; // Tiêu đề trang

        // Lấy trang cần chỉnh sửa theo ID (được truyền qua request)
        $page = Page::findOrFail($request->id); // Nếu không tìm thấy trang, sẽ trả về lỗi 404

        // Trả về view để chỉnh sửa trang với dữ liệu trang và tiêu đề
        return view('backend.page.edit', compact('title', 'page'));
    }

    // Cập nhật thông tin trang
    public function pageUpdate(PagesRequest $request)
    {
        // Sử dụng dịch vụ PageService để cập nhật trang
        $isSuccess = $this->page->update($request);
        
        // Nếu có lỗi trong quá trình cập nhật, dừng xử lý và trả về lỗi
        if($isSuccess['type'] === 'error'){
            abort($isSuccess['message']); // Dừng và hiển thị thông báo lỗi
        }
    
        // Nếu cập nhật thành công, quay lại trang trước với thông báo thành công
        return back()->with('success', 'Page Updated Successfully');
    }

    // Xóa một trang
    public function pageDelete(Request $request, Page $id)
    {
        // Kiểm tra nếu tên trang là 'home', không cho phép xóa trang này
        if ($id->name == 'home') {
            return back()->with('error', 'At least One page is Required'); // Trả về thông báo lỗi
        }

        // Xóa trang
        $id->delete();

        // Quay lại trang trước với thông báo thành công
        return back()->with('success', 'Page Deleted Successfully');
    }
}
