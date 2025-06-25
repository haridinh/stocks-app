<?php

namespace App\Http\Controllers;

// Các thư viện và lớp cần thiết
use App\Helpers\Helper\Helper;          // Lớp Helper để lấy các phương thức trợ giúp chung (như pagination, theme...)
use App\Models\Plan;                    // Mô hình dữ liệu cho các gói (Plan) mà người dùng có thể đăng ký
use App\Services\UserPlanService;       // Dịch vụ xử lý các chức năng liên quan đến kế hoạch (Plan) của người dùng
use Illuminate\Http\Request;            // Lớp Request của Laravel để xử lý các yêu cầu HTTP

class PlanController extends Controller
{
    // Khai báo thuộc tính $planservice để lưu dịch vụ liên quan đến kế hoạch người dùng
    protected $planservice;

    // Hàm khởi tạo, nhận vào đối tượng UserPlanService
    public function __construct(UserPlanService $planservice)
    {
        // Gán đối tượng UserPlanService cho thuộc tính $planservice
        $this->planservice = $planservice;
    }

    // Hàm xử lý việc hiển thị danh sách các kế hoạch (Plans) có sẵn
    public function plans()
    {
        // Cấu hình tiêu đề cho trang
        $data['title'] = 'Plans';

        // Lấy danh sách các kế hoạch có trạng thái 'true' (hoạt động) và phân trang theo cấu hình từ Helper
        $data['plans'] = Plan::whereStatus(true)->paginate(Helper::pagination());

        // Trả về view với tên đã được cấu hình trong Helper và truyền dữ liệu danh sách kế hoạch đi kèm
        return view(Helper::theme() . 'user.plans')->with($data);
    }

    // Hàm xử lý khi người dùng đăng ký (subscribe) vào một kế hoạch
    public function subscribe(Request $request)
    {
        // Gọi dịch vụ để xử lý việc đăng ký kế hoạch của người dùng, truyền vào request của người dùng
        $isSuccess = $this->planservice->subscribe($request);

        // Kiểm tra nếu kết quả trả về có lỗi
        if ($isSuccess['type'] == 'error') {
            // Nếu có lỗi, chuyển hướng về trang trước đó và hiển thị thông báo lỗi
            return redirect()->back()->with('error', $isSuccess['message']);
        }

        // Kiểm tra nếu kết quả trả về yêu cầu chuyển hướng (redirect)
        if ($isSuccess['type'] == 'redirect') {
            // Nếu có yêu cầu chuyển hướng, chuyển người dùng đến URL mới
            return redirect()->to($isSuccess['message']);
        }

        // Nếu không có lỗi, chuyển hướng về trang trước đó và hiển thị thông báo thành công
        return redirect()->back()->with('success', $isSuccess['message']);
    }
}
