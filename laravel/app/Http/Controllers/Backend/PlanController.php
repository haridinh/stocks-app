<?php

namespace App\Http\Controllers\Backend; // Định nghĩa namespace cho controller, giúp phân loại mã nguồn

// Import các lớp và helper cần thiết
use App\Helpers\Helper\Helper; // Lớp Helper chứa các hàm tiện ích, ví dụ: pagination
use App\Models\Plan; // Lớp Model Plan dùng để làm việc với cơ sở dữ liệu của bảng 'plans'
use Illuminate\Http\Request; // Lớp Request của Laravel để nhận dữ liệu từ client
use App\Http\Controllers\Controller; // Lớp Controller cơ bản của Laravel
use App\Http\Requests\PlanRequest; // Lớp PlanRequest dùng để xử lý và xác thực dữ liệu nhập vào
use App\Services\PlanService; // Lớp Service PlanService chứa các logic kinh doanh liên quan đến Plan

class PlanController extends Controller
{
    // Khai báo thuộc tính chứa đối tượng PlanService
    protected $plan;

    // Constructor: Hàm khởi tạo PlanController với một đối tượng PlanService
    public function __construct(PlanService $plan)
    {
        $this->plan = $plan; // Gán đối tượng PlanService vào thuộc tính $plan
    }

    // Hàm hiển thị danh sách các plan
    public function index(Request $request)
    {
        // Đặt tiêu đề trang
        $data['title'] = 'All Plans';

        // Lấy danh sách kế hoạch từ cơ sở dữ liệu, tìm kiếm theo từ khóa và phân trang
        $data['plans'] = Plan::search($request->search) // Tìm kiếm theo từ khóa
            ->orderBy('id','ASC') // Sắp xếp theo ID tăng dần
            ->paginate(Helper::pagination()); // Phân trang, sử dụng hàm pagination từ Helper

        // Trả về view backend.plan.index kèm theo dữ liệu
        return view('backend.plan.index')->with($data);
    }

    // Hàm hiển thị form tạo mới kế hoạch
    public function create()
    {
        // Đặt tiêu đề trang
        $data['title'] = 'Create Plan';

        // Trả về view backend.plan.create kèm theo dữ liệu
        return view('backend.plan.create')->with($data);
    }

    // Hàm xử lý việc lưu kế hoạch mới
    public function store(PlanRequest $request)
    {
        // Gọi phương thức createPlan từ PlanService để tạo kế hoạch mới
        $this->plan->createPlan($request);

        // Quay lại trang danh sách kế hoạch với thông báo thành công
        return redirect()->route('admin.plan.index')->with('success', 'Plan Created Successfully');
    }

    // Hàm hiển thị form chỉnh sửa kế hoạch
    public function edit(Plan $plan)
    {
        // Đặt tiêu đề trang
        $title = 'Edit Plan';

        // Trả về view backend.plan.edit kèm theo dữ liệu kế hoạch cần chỉnh sửa
        return view('backend.plan.edit', compact('title', 'plan'));
    }

    // Hàm xử lý việc cập nhật kế hoạch
    public function update(PlanRequest $request)
    {
        // Gọi phương thức updatePlan từ PlanService để cập nhật kế hoạch
        $isSuccess = $this->plan->updatePlan($request);

        // Kiểm tra nếu có lỗi trong quá trình cập nhật
        if ($isSuccess['type'] === 'error') {
            // Nếu có lỗi, quay lại với thông báo lỗi
            return redirect()->back()->with('error', $isSuccess['message']);
        }

        // Nếu thành công, quay lại trang danh sách kế hoạch với thông báo thành công
        return redirect()->route('admin.plan.index')->with('success', 'Plan Updated Successfully');
    }

    // Hàm thay đổi trạng thái của kế hoạch
    public function planStatusChange($id)
    {
        // Gọi phương thức changeStatus từ PlanService để thay đổi trạng thái của kế hoạch
        $isSuccess = $this->plan->changeStatus($id);

        // Nếu thay đổi trạng thái thành công, trả về thông báo thành công dưới dạng JSON
        if ($isSuccess['type'] === 'success') {
            return response()->json(['success' =>  $isSuccess['message']]);
        }
    }
}
