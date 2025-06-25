<?php

namespace App\Http\Controllers;

use App\Helpers\Helper\Helper;           // Lớp Helper để lấy các phương thức trợ giúp chung (như theme, file path, v.v.)
use App\Http\Requests\TicketRequest;     // Lớp yêu cầu xác thực khi người dùng tạo hoặc cập nhật ticket
use App\Models\Ticket;                   // Mô hình dữ liệu cho Ticket (phiếu hỗ trợ)
use App\Models\TicketReply;              // Mô hình dữ liệu cho các câu trả lời của Ticket
use App\Services\UserTicketService;      // Dịch vụ xử lý các thao tác liên quan đến ticket của người dùng
use Illuminate\Http\Request;             // Lớp Request của Laravel để xử lý các yêu cầu HTTP
use Illuminate\Support\Facades\Auth;      // Lớp Auth của Laravel để làm việc với người dùng đã đăng nhập

class TicketController extends Controller
{
    protected $ticket; // Khai báo thuộc tính để lưu dịch vụ liên quan đến Ticket

    // Hàm khởi tạo để nhận đối tượng UserTicketService
    public function __construct(UserTicketService $ticket)
    {
        // Gán đối tượng UserTicketService vào thuộc tính $ticket
        $this->ticket = $ticket;
    }

    // Hàm xử lý việc hiển thị danh sách ticket của người dùng
    public function index()
    {
        // Cấu hình tiêu đề cho trang
        $data['title'] = "Support Ticket";

        // Lấy tất cả các ticket của người dùng hiện tại, kèm theo các câu trả lời liên quan
        $data['tickets'] = Ticket::whereUserId(Auth::user()->id)->with('ticketReplies')->paginate();

        // Lấy số lượng ticket theo từng trạng thái: đang chờ (pending), đã trả lời (answered), và đã đóng (closed)
        $data['tickets_pending'] = Ticket::whereUserId(Auth::user()->id)->whereStatus('2')->count();
        $data['tickets_answered'] = Ticket::whereUserId(Auth::user()->id)->whereStatus('3')->count();
        $data['tickets_closed'] = Ticket::whereUserId(Auth::user()->id)->whereStatus('1')->count();
        $data['tickets_all'] = Ticket::whereUserId(Auth::user()->id)->count();

        // Trả về view danh sách ticket của người dùng
        return view(Helper::theme() . 'user.ticket.list')->with($data);
    }

    // Hàm xử lý việc tạo mới một ticket
    public function store(TicketRequest $request)
    {
        // Gọi dịch vụ để tạo ticket mới, truyền vào dữ liệu từ request
        $isSuccess = $this->ticket->create($request);

        // Nếu tạo thành công, chuyển hướng đến danh sách ticket và hiển thị thông báo thành công
        if ($isSuccess['type'] === 'success')
            return redirect()->route('user.ticket.index')->with('success', $isSuccess['message']);
    }

    /**
     * Hiển thị chi tiết của một ticket cụ thể
     *
     * @param  int  $id - ID của ticket
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        // Cấu hình tiêu đề cho trang
        $data['title'] = "Support Ticket Discussion";

        // Lấy thông tin của ticket theo ID
        $data['ticket'] = Ticket::find($id);

        // Lấy tất cả các ticket của người dùng, kèm theo các câu trả lời liên quan
        $data['tickets'] = Ticket::whereUserId(Auth::user()->id)->with('ticketReplies')->get();

        // Lấy các câu trả lời của ticket cụ thể, sắp xếp theo thời gian mới nhất
        $data['ticket_reply'] = TicketReply::whereTicketId($data['ticket']->id)->latest()->get();

        // Trả về view chi tiết ticket
        return view(Helper::theme() . 'user.ticket.show')->with($data);
    }

    // Hàm xử lý việc cập nhật ticket
    public function update(TicketRequest $request, $id)
    {
        // Gọi dịch vụ để cập nhật ticket, truyền vào dữ liệu từ request và ID của ticket
        $isSuccess = $this->ticket->update($request, $id);

        // Nếu cập nhật thành công, chuyển hướng về danh sách ticket và hiển thị thông báo thành công
        if ($isSuccess['type'] === 'success')
            return redirect()->route('user.ticket.index')->with('success', $isSuccess['message']);
    }

    // Hàm xử lý việc xóa ticket
    public function destroy($id)
    {
        // Gọi dịch vụ để xóa ticket theo ID
        $isSuccess = $this->ticket->delete($id);

        // Nếu xóa thành công, quay lại trang trước đó và hiển thị thông báo thành công
        if ($isSuccess['type'] === 'success')
            return redirect()->back()->with('success', $isSuccess['message']);
    }

    // Hàm xử lý việc trả lời cho một ticket
    public function reply(Request $request)
    {
        // Gọi dịch vụ để trả lời cho ticket, truyền vào dữ liệu từ request
        $isSuccess = $this->ticket->reply($request);

        // Nếu trả lời thành công, quay lại trang trước đó và hiển thị thông báo thành công
        if ($isSuccess['type'] === 'success')
            return redirect()->back()->with('success', $isSuccess['message']);
    }

    // Hàm xử lý việc thay đổi trạng thái của ticket (đóng ticket)
    public function statusChange($id)
    {
        // Lấy ticket theo ID
        $ticket = Ticket::find($id);

        // Đổi trạng thái của ticket thành 'đã đóng' (status = 1)
        $ticket->status = 1;
        $ticket->save();

        // Chuyển hướng về danh sách ticket và hiển thị thông báo đóng ticket thành công
        return redirect()->route('user.ticket.index')->with('success', 'Closed conversation Successfully');
    }

    // Hàm xử lý việc lọc và hiển thị các ticket theo trạng thái
    public function ticketStatus(Request $request)
    {
        // Mảng ánh xạ trạng thái của ticket
        $ticketStatus = [
            'answered' => 3,  // Đã trả lời
            'pending' => 2,   // Đang chờ
            'closed' => 1     // Đã đóng
        ];

        // Cấu hình tiêu đề cho trang dựa trên trạng thái
        $data['title'] = "{$request->status} Support Ticket";

        // Lọc ticket theo trạng thái được chọn và phân trang kết quả
        $data['tickets'] = Ticket::whereUserId(Auth::user()->id)
            ->whereStatus($ticketStatus[$request->status])
            ->with('ticketReplies')
            ->paginate();

        // Lấy số lượng ticket theo từng trạng thái
        $data['tickets_pending'] = Ticket::whereUserId(Auth::user()->id)->whereStatus('2')->count();
        $data['tickets_answered'] = Ticket::whereUserId(Auth::user()->id)->whereStatus('3')->count();
        $data['tickets_closed'] = Ticket::whereUserId(Auth::user()->id)->whereStatus('1')->count();
        $data['tickets_all'] = Ticket::whereUserId(Auth::user()->id)->count();

        // Trả về view danh sách ticket của người dùng
        return view(Helper::theme() . 'user.ticket.list')->with($data);
    }

    // Hàm xử lý việc tải xuống file đính kèm của một câu trả lời trong ticket
    public function ticketDownload($id)
    {
        // Tìm câu trả lời của ticket theo ID
        $ticket = TicketReply::findOrFail($id);

        // Kiểm tra nếu có file đính kèm
        if ($ticket->file) {
            // Lấy đường dẫn đến file
            $file = Helper::filePath('Ticket', true) . '/' . $ticket->file;

            // Kiểm tra nếu file tồn tại trên hệ thống
            if (file_exists($file)) {
                // Trả về phản hồi tải xuống file
                return response()->download($file);
            }
        }
    }
}
