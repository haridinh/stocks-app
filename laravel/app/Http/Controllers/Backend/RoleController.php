<?php

namespace App\Http\Controllers\Backend; // Định nghĩa namespace cho controller, giúp phân loại mã nguồn

// Import các lớp và helper cần thiết
use App\Helpers\Helper\Helper; // Lớp Helper chứa các hàm tiện ích, ví dụ: phân trang
use App\Http\Controllers\Controller; // Lớp Controller cơ bản của Laravel
use Illuminate\Http\Request; // Lớp Request của Laravel để nhận dữ liệu từ client
use Spatie\Permission\Models\Permission; // Lớp Permission của package Spatie dùng để quản lý quyền
use Spatie\Permission\Models\Role; // Lớp Role của package Spatie dùng để quản lý vai trò (roles)

class RoleController extends Controller
{
    // Hàm hiển thị trang quản lý vai trò (Role)
    public function index(Request $request)
    {
        // Đặt tiêu đề trang
        $data['title'] = 'Manage Roles';

        // Lấy tất cả các permission cùng với các roles liên quan, sắp xếp theo thứ tự mới nhất
        $data['permissions'] = Permission::with('roles')->latest()->get();

        // Lấy danh sách các roles, có thể lọc theo tên role nếu có tham số 'role' trong request
        $data['roles'] = Role::when($request->role, function($item) use($request){
            // Lọc theo tên role nếu tham số 'role' được gửi từ client
            $item->where('name', $request->role);
        })->where('name', '!=', 'admin') // Loại bỏ role có tên là 'admin'
        ->latest() // Sắp xếp theo thứ tự mới nhất
        ->with('permissions', 'users') // Lấy kèm các permissions và users liên quan
        ->paginate(Helper::pagination()); // Phân trang, sử dụng hàm pagination từ Helper

        // Trả về view 'backend.role.index' kèm theo dữ liệu đã xử lý
        return view('backend.role.index')->with($data);
    }

    // Hàm xử lý việc lưu vai trò mới
    public function store(Request $request)
    {
        // Xác thực dữ liệu từ request
        $request->validate([
            'role' => 'required|max:100|unique:roles,name', // Kiểm tra tên vai trò phải có, tối đa 100 ký tự, và không trùng lặp trong bảng 'roles'
            'permission' => 'required|array' // Kiểm tra quyền (permissions) phải có và là một mảng
        ]);

        // Tạo mới một role với tên và guard name là 'admin'
        $role = Role::create(['name' => $request->role, 'guard_name' => 'admin']);

        // Gán các quyền cho role vừa tạo
        $role->givePermissionTo($request->permission);

        // Quay lại trang trước đó với thông báo thành công
        return redirect()->back()->with('success', 'Successfully Create Role');
    }

    // Hàm xử lý việc cập nhật thông tin vai trò
    public function update(Request $request, $id)
    {
        // Tìm role theo ID
        $role = Role::findOrFail($id);

        // Xác thực dữ liệu từ request
        $request->validate([
            'role' => 'required|max:100|unique:roles,name,' . $role->id, // Kiểm tra tên vai trò phải có và không trùng lặp ngoại trừ role hiện tại
            'permission' => 'required|array' // Kiểm tra quyền (permissions) phải có và là một mảng
        ]);

        // Cập nhật tên của role
        $role->update(['name' => $request->role]);

        // Đồng bộ lại các permissions của role với dữ liệu trong request
        $role->syncPermissions($request->permission);

        // Quay lại trang trước đó với thông báo thành công
        return redirect()->back()->with('success', 'Successfully Updated Role');
    }

    // Hàm edit (chưa có chức năng, trả về lỗi 404)
    public function edit()
    {
        abort(404); // Trả về lỗi 404 nếu gọi phương thức này
    }

    // Hàm show (chưa có chức năng, trả về lỗi 404)
    public function show()
    {
        abort(404); // Trả về lỗi 404 nếu gọi phương thức này
    }

    // Hàm destroy (chưa có chức năng, trả về lỗi 404)
    public function destroy()
    {
        abort(404); // Trả về lỗi 404 nếu gọi phương thức này
    }
}
