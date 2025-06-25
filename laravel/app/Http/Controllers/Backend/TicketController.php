<?php

namespace App\Http\Controllers\Backend; // Định nghĩa namespace cho controller, giúp phân loại mã nguồn

// Import các lớp cần thiết
use App\Helpers\Helper\Helper; // Lớp Helper chứa các hàm tiện ích như phân trang, lưu tệp tin
use App\Http\Controllers\Controller; // Lớp Controller cơ bản của Laravel
use App\Models\Ticket; // Lớp Ticket, sử dụng để làm việc với bảng tickets
use App\Models\TicketReply; // Lớp TicketReply, sử dụng để làm việc với bảng ticket_replies
use App\Models\User; // Lớp User, sử dụng để làm việc với bảng users (mặc dù không được sử dụng trong mã này)
use Illuminate\Http\Request; // Lớp Request của Laravel để nhận dữ liệu từ client
use Illuminate\Support\Facades\Auth; // Facade Auth của Laravel để làm việc với xác thực người dùng
use Illuminate\Support\Facades\Validator; // Facade Validator của Laravel để kiểm tra dữ liệu đầu vào

class TicketController extends Controller
{
    // Hàm hiển thị danh sách tất cả các vé hỗ trợ
    public function index(Request $request)
    {
        $data['title'] = "All Ticket"; // Tiêu đề của trang

        // Khởi tạo query builder cho bảng Ticket
        $tickets = Ticket::query();

        // Lọc vé theo người dùng nếu có tham số user trong request
        if ($request->user) {
            $tickets->where('user_id', $request->user);
        }

        // Lọc vé theo mã hỗ trợ nếu có tham số tìm kiếm
        if ($request->search) {
            $tickets->where('support_id', 'LIKE', '%' . $request->search . '%');
        } elseif ($request->status) {
            // Lọc vé theo trạng thái nếu có tham số status
            $status  = $request->status === 'closed' ? 1 : ($request->status === 'pending' ? 2 : 3);
            $tickets->where('status', $status);
        }

        // Lấy danh sách vé với các liên kết (ticketReplies, user), sắp xếp theo thứ tự mới nhất và phân trang
        $data['tickets'] = $tickets->with('ticketReplies', 'user')->latest()->paginate(Helper::pagination());

        // Trả về view 'backend.ticket.list' với dữ liệu vé
        return view('backend.ticket.list')->with($data);
    }

    // Hàm lọc vé theo trạng thái (pending, answered, closed)
    public function filterByStatus(Request $request)
    {
        $data['title'] = "{$request->status} Ticket"; // Tiêu đề trang tùy thuộc vào trạng thái

        // Khởi tạo query builder cho bảng Ticket
        $tickets = Ticket::query();

        // Lọc vé theo trạng thái từ tham số status
        if ($request->status === 'pending') {
            $tickets->where('status', 2); // Trạng thái pending
        } elseif ($request->status === 'answered') {
            $tickets->where('status', 3); // Trạng thái answered
        } else {
            $tickets->where('status', 1); // Trạng thái closed
        }

        // Lấy danh sách vé với các liên kết (ticketReplies, user), sắp xếp theo thứ tự mới nhất và phân trang
        $data['tickets'] = $tickets->latest()->with('ticketReplies', 'user')->paginate(Helper::pagination());

        // Trả về view 'backend.ticket.list' với dữ liệu vé
        return view('backend.ticket.list')->with($data);
    }

    // Hàm hiển thị chi tiết thảo luận của một vé hỗ trợ
    public function show($id)
    {
        $data['title'] = "Support Ticket Discussion"; // Tiêu đề của trang

        // Lấy vé hỗ trợ theo ID
        $data['ticket'] = Ticket::find($id);

        // Lấy tất cả các phản hồi liên quan đến vé đó
        $data['ticket_reply'] = TicketReply::whereTicketId($data['ticket']->id)->latest()->with('ticket')->get();

        // Trả về view 'backend.ticket.show' với dữ liệu vé và phản hồi
        return view('backend.ticket.show')->with($data);
    }

    // Hàm xóa vé hỗ trợ và các phản hồi liên quan
    public function destroy($id)
    {
        // Lấy vé theo ID
        $ticket = Ticket::find($id);
        if ($ticket) {
            // Lấy tất cả các phản hồi của vé này
            $all_reply = TicketReply::whereTicketId($id)->get();
            if (count($all_reply) > 0) {
                // Duyệt qua tất cả các phản hồi và xóa các tệp đính kèm nếu có
                foreach ($all_reply as $reply) {
                    $item = TicketReply::find($reply->id);
                    if ($item->file) {
                        // Xóa tệp đính kèm nếu có
                        Helper::removeFile(Helper::filePath('Ticket', true) . $reply->file);
                    }
                    $item->delete(); // Xóa phản hồi
                }
            }
            $ticket->delete(); // Xóa vé hỗ trợ
        }

        // Quay lại trang trước đó với thông báo thành công
        return redirect()->back()->with('success', 'Ticket Deleted Successfully');
    }

    // Hàm trả lời một vé hỗ trợ
    public function reply(Request $request)
    {
        // Xác thực dữ liệu đầu vào
        $validator = Validator::make($request->all(), [
            'message' => 'required', // Yêu cầu trường message phải có nội dung
        ]);

        // Nếu xác thực không thành công, quay lại với lỗi
        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        // Tạo một phản hồi mới cho vé hỗ trợ
        $reply = new TicketReply();
        $reply->ticket_id = $request->ticket_id; // Gán ID của vé hỗ trợ
        $reply->admin_id = Auth::guard('admin')->user()->id; // Gán ID admin trả lời
        $reply->message = $request->message; // Gán nội dung phản hồi

        // Nếu có tệp đính kèm, lưu tệp và gán vào phản hồi
        if ($request->has('image')) {
            $image = Helper::saveImage($request->image, Helper::filePath('Ticket', true));
            $reply->file = $image;
        }

        // Lưu phản hồi vào cơ sở dữ liệu
        $reply->save();

        // Cập nhật trạng thái của vé thành "answered" (trạng thái 3)
        Ticket::findOrFail($request->ticket_id)->update(['status' => 3]);

        // Quay lại trang trước đó với thông báo thành công
        return redirect()->back()->with('success', 'Reply Created Successfully');
    }
}
