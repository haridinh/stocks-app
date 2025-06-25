<?php

namespace App\Http\Controllers;

// Các thư viện và lớp cần thiết
use App\Helpers\Helper\Helper;                // Lớp Helper của ứng dụng
use App\Http\Requests\UserWithdrawRequest;    // Yêu cầu xác thực khi rút tiền của người dùng
use App\Models\Withdraw;                      // Mô hình dữ liệu cho việc rút tiền
use App\Models\WithdrawGateway;               // Mô hình dữ liệu cho cổng thanh toán rút tiền
use App\Services\UserWithdrawService;         // Dịch vụ xử lý việc rút tiền của người dùng
use Illuminate\Http\Request;                  // Lớp Request của Laravel để xử lý yêu cầu HTTP

class PayoutController extends Controller
{
    // Khai báo thuộc tính $withdrawservice để lưu dịch vụ rút tiền
    protected $withdrawservice;

    // Hàm khởi tạo, nhận vào đối tượng UserWithdrawService
    public function __construct(UserWithdrawService $withdrawservice)
    {
        // Gán đối tượng UserWithdrawService cho thuộc tính $withdrawservice
        $this->withdrawservice = $withdrawservice;
    }

    // Hàm xử lý trang rút tiền của người dùng
    public function withdraw()
    {
        // Cấu hình dữ liệu cho trang view, tiêu đề là 'Withdraw Money'
        $data['title'] = 'Withdraw Money';

        // Lấy danh sách các cổng thanh toán rút tiền có trạng thái là 1 (có sẵn để sử dụng) và sắp xếp theo thứ tự mới nhất
        $data['withdraws'] = WithdrawGateway::where('status', 1)->latest()->get();

        // Trả về view với tên đã được cấu hình trong Helper và truyền dữ liệu đi kèm
        return view(Helper::theme() . 'user.withdraw.index')->with($data);
    }

    // Hàm xử lý khi người dùng hoàn tất yêu cầu rút tiền
    public function withdrawCompleted(UserWithdrawRequest $request)
    {
        // Gọi dịch vụ để xử lý việc rút tiền, truyền vào request của người dùng
        $isSuccess = $this->withdrawservice->makeWithdraw($request);

        // Kiểm tra nếu kết quả trả về có lỗi
        if($isSuccess['type'] === 'error'){
            // Nếu có lỗi, chuyển hướng về trang trước đó và hiển thị thông báo lỗi
            return redirect()->back()->with('error', $isSuccess['message']);
        }

        // Nếu rút tiền thành công, chuyển hướng về trang trước đó và hiển thị thông báo thành công
        return redirect()->back()->with('success', $isSuccess['message']);
    }

    // Hàm xử lý lấy thông tin cổng rút tiền theo id
    public function withdrawFetch(Request $request)
    {
        // Tìm cổng thanh toán rút tiền theo id được truyền từ request, nếu không tìm thấy sẽ báo lỗi 404
        $withdraw = WithdrawGateway::findOrFail($request->id);

        // Trả về dữ liệu cổng thanh toán rút tiền
        return $withdraw;
    }

}
