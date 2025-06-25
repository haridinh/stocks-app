<?php

// Khai báo namespace cho controller, đặt trong thư mục Backend của ứng dụng
namespace App\Http\Controllers\Backend;

// Nhập các class và helper cần thiết
use App\Helpers\Helper\Helper; // Helper để hỗ trợ các chức năng chung (lưu ảnh, phân trang, v.v.)
use App\Http\Controllers\Controller; // Class cơ sở cho controller
use App\Jobs\SendEmailJob; // Job xử lý gửi email
use App\Jobs\SendSubscriberEmail; // Job gửi email cho subscriber
use App\Jobs\SendSubscriberMail; // Job gửi email hàng loạt cho subscriber
use App\Models\Admin; // Model đại diện cho bảng admins
use App\Models\Subscriber; // Model đại diện cho bảng subscribers
use App\Models\Template; // Model đại diện cho bảng templates (không sử dụng trong mã này)
use App\Notifications\DepositNotification; // Notification cho giao dịch nạp tiền
use App\Notifications\KycUpdateNotification; // Notification cho cập nhật KYC
use App\Notifications\PlanSubscriptionNotification; // Notification cho đăng ký gói dịch vụ
use App\Notifications\TicketNotification; // Notification cho ticket hỗ trợ
use App\Notifications\WithdrawNotification; // Notification cho giao dịch rút tiền
use Illuminate\Http\Request; // Class xử lý request HTTP
use Spatie\Permission\Models\Role; // Model Role từ package Spatie để quản lý vai trò

// Class AdminController kế thừa từ Controller
class AdminController extends Controller
{
    // Phương thức index: Hiển thị danh sách admin
    public function index(Request $request)
    {
        // Đặt tiêu đề trang
        $data['title'] = 'Manage Admins';

        // Lấy danh sách admin với phân trang, lọc theo từ khóa tìm kiếm (nếu có)
        $data['admins'] = Admin::when($request->search, function ($admin) use ($request) {
            // Tìm kiếm theo username hoặc email
            $admin->where(function ($item) use ($request) {
                $item->where('username', $request->search)
                    ->orWhere('email', $request->search);
            });
        })->where('username', '!=', 'admin') // Loại bỏ admin mặc định
            ->latest() // Sắp xếp theo mới nhất
            ->with('roles') // Lấy thông tin vai trò liên quan
            ->paginate(Helper::pagination()); // Phân trang với số lượng từ Helper

        // Trả về view danh sách admin
        return view('backend.admins.index')->with($data);
    }

    // Phương thức create: Hiển thị form tạo admin mới
    public function create()
    {
        // Đặt tiêu đề trang
        $data['title'] = 'Create Admins';

        // Lấy danh sách vai trò (trừ vai trò 'admin')
        $data['roles'] = Role::where('name', '!=', 'admin')->latest()->get();

        // Trả về view form tạo admin
        return view('backend.admins.create')->with($data);
    }

    // Phương thức store: Xử lý lưu admin mới
    public function store(Request $request)
    {
        // Xác thực dữ liệu đầu vào
        $request->validate([
            'username' => 'required|unique:admins,username', // Username bắt buộc, duy nhất
            'email' => 'required|email|unique:admins,email', // Email bắt buộc, hợp lệ, duy nhất
            'password' => 'required|min:6|confirmed', // Mật khẩu bắt buộc, tối thiểu 6 ký tự, khớp với xác nhận
            'roles' => 'required|array', // Vai trò bắt buộc, dạng mảng
            'admin_image' => 'nullable|mimes:jpg,png,jpeg' // Ảnh admin tùy chọn, chỉ chấp nhận định dạng hình ảnh
        ]);

        // Tạo admin mới
        $admin = Admin::create([
            'username' => $request->username,
            'email' => $request->email,
            'password' => bcrypt($request->password), // Mã hóa mật khẩu
            'image' => $request->has('admin_image') ? Helper::saveImage($request->admin_image, Helper::filePath('admin')) : '' // Lưu ảnh nếu có
        ]);

        // Gán vai trò cho admin
        $admin->assignRole($request->roles);

        // Chuyển hướng về trang trước với thông báo thành công
        return redirect()->back()->with('success', 'Admin created Successfully');
    }

    // Phương thức edit: Hiển thị form chỉnh sửa admin
    public function edit($id)
    {
        // Tìm admin theo ID, loại trừ admin mặc định
        $data['admin'] = Admin::where('username', '!=', 'admin')->findOrFail($id);

        // Đặt tiêu đề trang
        $data['title'] = 'Edit Admins';

        // Lấy danh sách vai trò
        $data['roles'] = Role::latest()->get();

        // Trả về view chỉnh sửa admin
        return view('backend.admins.edit')->with($data);
    }

    // Phương thức update: Xử lý cập nhật admin
    public function update(Request $request, $id)
    {
        // Tìm admin theo ID, loại trừ admin mặc định
        $admin = Admin::where('username', '!=', 'admin')->findOrFail($id);

        // Xác thực dữ liệu đầu vào
        $request->validate([
            'username' => 'required|unique:admins,username,' . $admin->id, // Username duy nhất, trừ chính nó
            'email' => 'required|email|unique:admins,email,' . $admin->id, // Email duy nhất, trừ chính nó
            'password' => 'nullable|min:6|confirmed', // Mật khẩu tùy chọn, nếu có thì tối thiểu 6 ký tự
            'roles' => 'required|array', // Vai trò bắt buộc
            'admin_image' => 'nullable|mimes:jpg,png,jpeg' // Ảnh tùy chọn
        ]);

        // Cập nhật thông tin admin
        $admin->update([
            'name' => $request->username,
            'username' => $request->username,
            'email' => $request->email,
            'password' => $request->password != null ? bcrypt($request->password) : $admin->password, // Cập nhật mật khẩu nếu có
            'image' => $request->has('admin_image') ? Helper::saveImage($request->admin_image, Helper::filePath('admins')) : $admin->image // Cập nhật ảnh nếu có
        ]);

        // Đồng bộ vai trò
        $admin->syncRoles($request->roles);

        // Chuyển hướng về trang trước với thông báo thành công
        return redirect()->back()->with('success', 'Successfully updated Admins');
    }

    // Phương thức notifications: Hiển thị danh sách thông báo
    public function notifications()
    {
        // Lấy các thông báo chưa đọc của admin hiện tại
        $data['notifications'] = auth()->guard('admin')->user()->unreadNotifications()->paginate(Helper::pagination(), ['*'], 'notifications');

        // Lấy thông báo chưa đọc theo loại cụ thể
        $data['depositNotifications'] = auth()->guard('admin')->user()->unreadNotifications()->where('type', DepositNotification::class)->paginate(Helper::pagination(), ['*'], 'depositNotifications');
        $data['subscriptionNotifications'] = auth()->guard('admin')->user()->unreadNotifications()->where('type', PlanSubscriptionNotification::class)->paginate(Helper::pagination(), ['*'], 'subscriptionNotifications');
        $data['withdrawNotifications'] = auth()->guard('admin')->user()->unreadNotifications()->where('type', WithdrawNotification::class,)->paginate(Helper::pagination(), ['*', 'withdrawNotifications']);
        $data['ticketNotifications'] = auth()->guard('admin')->user()->unreadNotifications()->where('type', TicketNotification::class)->paginate(Helper::pagination(), ['*'], 'ticketsNotifications');
        $data['kycNotifications'] = auth()->guard('admin')->user()->unreadNotifications()->where('type', KycUpdateNotification::class)->paginate(Helper::pagination(), ['*'], 'kycNotifications');

        // Đặt tiêu đề trang
        $data['title'] = 'Notificaciones';

        // Trả về view thông báo
        return view('backend.notifications')->with($data);
    }

    // Phương thức SignlemarkNotification: Đánh dấu một thông báo là đã đọc
    public function SignlemarkNotification(Request $request, $id)
    {
        // Tìm thông báo chưa đọc theo ID
        $notification = auth()->guard('admin')->user()
            ->unreadNotifications()
            ->where('id', $id)->get();

        // Đánh dấu là đã đọc
        $notification->markAsRead();

        // Trả về phản hồi JSON
        return response()->json(['success' => true, 'id' => $request->id]);
    }

    // Phương thức changeStatus: Thay đổi trạng thái admin (active/inactive)
    public function changeStatus($id)
    {
        // Tìm admin theo ID
        $admin = Admin::find($id);

        // Đổi trạng thái (true thành false và ngược lại)
        $admin->status = !$admin->status;

        // Lưu thay đổi
        $admin->save();

        // Trả về phản hồi JSON
        return redirect()->response()->json(['success' => true]);
    }

    // Phương thức subscribers: Hiển thị danh sách subscribers
    public function subscribers()
    {
        // Đặt tiêu đề trang
        $title = "Newsletter Subscriber";

        // Lấy danh sách subscriber với phân trang
        $subscribers = Subscriber::latest()->paginate(Helper::pagination());

        // Trả về view danh sách subscriber
        return view('backend.subscriber', compact('subscribers', 'title'));
    }

    // Phương thức bulkMail: Gửi email hàng loạt cho subscribers
    public function bulkMail(Request $request)
    {
        // Xác thực dữ liệu đầu vào
        $data = request->validate([
            'subject' => 'required', // Tiêu đề email bắt buộc
            'message' => 'required' // Nội dung email bắt buộc
        ]);

        // Gửi job gửi email hàng loạt
        SendSubscriberMail::dispatch($data->email);

        // Chuyển hướng về danh sách subscriber với thông báo thành công
        return redirect()->route('admin.subscribers')->with('success', 'Successfully Send Mail');
    }

    // Phương thức singleMail: Gửi email cho một subscriber
    public function singleMail(Request $request)
    {
        // Xác thực dữ liệu
        $request->validate([
            'subject' => 'required', // Tiêu đề bắt buộc
            'message' => 'required' // Nội dung bắt buộc
        ]);

        // Tìm subscriber theo email
        $subscribers = Subscriber::where('email', $request->email)->first();

        // Chuẩn bị dữ liệu email
        $data['subject'] = $request->subject;
        $data['message'] = $request->message;
        $data['email'] = $subscribers->email;
        $data['username'] = $subscribers->email;
        $data['app_name'] = Helper::config()->appname;

        // Gửi email
        Helper::commonMail($data);

        // Chuyển hướng về danh sách subscriber với thông báo thành công
        return redirect()->route('admin.subscribers')->with('success', 'Successfully Send Mail');
    }
}