<?php

namespace App\Http\Controllers;

// Import các lớp và dịch vụ cần thiết
use App\Helpers\Helper\Helper;
use App\Http\Requests\UserMoneyTransferRequest;
use App\Models\MoneyTransfer;
use App\Services\UserMoneyTransferService;
use Illuminate\Http\Request;

class MoneyTransferController extends Controller
{
    // Khai báo thuộc tính lưu trữ dịch vụ chuyển tiền người dùng
    protected $transfer;

    // Khởi tạo controller với dịch vụ chuyển tiền
    public function __construct(UserMoneyTransferService $transfer)
    {
        $this->transfer = $transfer; // Gán dịch vụ vào thuộc tính của controller
    }

    // Hiển thị giao diện chuyển tiền
    public function transfer()
    {
        $data['title'] = 'Transfer Money'; // Tiêu đề trang

        return view(Helper::theme() . 'user.transfer_money')->with($data); // Trả về view chuyển tiền
    }

    // Thực hiện chuyển tiền từ người dùng (kèm xác thực thông qua UserMoneyTransferRequest)
    public function transferMoney(UserMoneyTransferRequest $request)
    {
        // Gọi dịch vụ để xử lý chuyển tiền
        $isSuccess = $this->transfer->transferMoney($request);

        // Kiểm tra kết quả trả về từ dịch vụ, nếu có lỗi thì trả thông báo lỗi
        if ($isSuccess['type'] === 'error') {
            return back()->with('error', $isSuccess['message']);
        }

        // Nếu thành công, trả thông báo thành công
        return back()->with('success', $isSuccess['message']);
    }

    // Hiển thị log chuyển tiền của người dùng (người gửi)
    public function transferMoneyLog(Request $request)
    {
        $data['title'] = 'Transfer Money Log'; // Tiêu đề trang

        // Lấy danh sách giao dịch chuyển tiền của người dùng (có thể lọc theo mã giao dịch hoặc ngày)
        $data['transferMoneys'] = MoneyTransfer::when($request->trx, function ($item) use ($request) {
            $item->where('trx', $request->trx); // Lọc theo mã giao dịch nếu có
        })->when($request->date, function ($item) use ($request) {
            $item->whereDate('created_at', $request->date); // Lọc theo ngày giao dịch nếu có
        })->where('sender_id', auth()->id()) // Lọc các giao dịch của người gửi là người dùng hiện tại
        ->latest() // Sắp xếp theo thứ tự giảm dần theo thời gian
        ->with('receiver') // Eager load thông tin người nhận
        ->paginate(Helper::pagination()); // Phân trang kết quả

        return view(Helper::theme() . 'user.transfermoney_log')->with($data); // Trả về view log chuyển tiền
    }

    // Hiển thị log nhận tiền của người dùng (người nhận)
    public function receiveMoneyLog(Request $request)
    {
        $data['title'] = 'Receive Money Log'; // Tiêu đề trang

        // Lấy danh sách giao dịch nhận tiền của người dùng (có thể lọc theo mã giao dịch hoặc ngày)
        $data['transferMoneys'] = MoneyTransfer::when($request->trx, function ($item) use ($request) {
            $item->where('trx', $request->trx); // Lọc theo mã giao dịch nếu có
        })->when($request->date, function ($item) use ($request) {
            $item->whereDate('created_at', $request->date); // Lọc theo ngày giao dịch nếu có
        })->where('receiver_id', auth()->id()) // Lọc các giao dịch của người nhận là người dùng hiện tại
        ->latest() // Sắp xếp theo thứ tự giảm dần theo thời gian
        ->with('sender') // Eager load thông tin người gửi
        ->paginate(Helper::pagination()); // Phân trang kết quả

        return view(Helper::theme() . 'user.transfermoney_log')->with($data); // Trả về view log nhận tiền
    }
}
