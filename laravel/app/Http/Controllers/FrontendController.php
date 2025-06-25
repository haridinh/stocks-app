<?php
namespace App\Http\Controllers; // Định nghĩa namespace cho controller, giúp phân loại mã nguồn

// Import các lớp cần thiết
use App\Helpers\Helper\Helper; // Lớp Helper cung cấp các phương thức hỗ trợ, ví dụ: lấy theme, gửi email,...
use App\Models\Configuration; // Lớp Configuration để truy vấn cài đặt cấu hình, ví dụ: theme của trang web
use App\Models\Content; // Lớp Content để truy vấn nội dung trang, bài viết,...
use App\Models\Page; // Lớp Page để truy vấn các trang tĩnh (home, về chúng tôi, ...)
use App\Models\Subscriber; // Lớp Subscriber dùng để lưu thông tin người đăng ký nhận bản tin
use Illuminate\Http\Request; // Lớp Request giúp xử lý yêu cầu HTTP từ người dùng
use Illuminate\Support\Facades\App; // Lớp App giúp xử lý thay đổi ngôn ngữ (locale) trong ứng dụng

class FrontendController extends Controller // Controller này dùng để xử lý các yêu cầu của người dùng đối với giao diện frontend
{

    // Phương thức index: Hiển thị trang chủ
    public function index()
    {
        // Lấy thông tin của trang home từ bảng Page
        $data['page'] = Page::where('name', 'home')->first();

        // Đặt tiêu đề trang là tên của trang home
        $data['title'] = $data['page']->name;

        // Trả về view trang chủ, sử dụng theme hiện tại (lấy từ helper)
        return view(Helper::theme() . 'home')->with($data);
    }

    // Phương thức page: Hiển thị các trang tĩnh theo slug
    public function page(Request $request)
    {
        // Lấy trang theo slug (đường dẫn thân thiện)
        $data['page'] = Page::where('slug', $request->pages)->first();

        // Nếu không tìm thấy trang, trả về lỗi 404
        if (!$data['page']) {
            abort(404);
        }

        // Đặt tiêu đề trang là tên của trang
        $data['title'] = "{$data['page']->name}";

        // Trả về view cho trang tĩnh, sử dụng theme hiện tại
        return view(Helper::theme() . 'pages')->with($data);
    }

    // Phương thức changeLanguage: Thay đổi ngôn ngữ của ứng dụng
    public function changeLanguage(Request $request)
    {
        // Thiết lập ngôn ngữ ứng dụng từ tham số lang trong yêu cầu
        App::setLocale($request->lang);

        // Lưu ngôn ngữ vào session
        session()->put('locale', $request->lang);

        // Quay lại trang trước đó và thông báo thay đổi ngôn ngữ thành công
        return redirect()->back()->with('success', __('Successfully Changed Language'));
    }

    // Phương thức blogDetails: Hiển thị chi tiết bài blog
    public function blogDetails($id)
    {
        // Lấy theme hiện tại từ cấu hình ứng dụng
        $theme = Configuration::first()->theme;

        // Thiết lập tiêu đề trang là "Recent Blog"
        $data['title'] = "Recent Blog";

        // Lấy thông tin bài blog theo theme và id
        $data['blog'] = Content::where('theme', $theme)->where('name', 'blog')->where('id', $id)->first();

        // Lấy các bài blog gần đây để hiển thị
        $data['recentblog'] = Content::where('theme', $theme)->where('name', 'blog')->where('type', 'iteratable')->latest()->limit(6)->paginate(Helper::pagination());

        // Chuẩn bị liên kết chia sẻ trên các mạng xã hội (Facebook, Twitter, LinkedIn, Telegram, WhatsApp, Reddit)
        $data['shareComponent'] = \Share::page(
            url()->current(),
            'Share',
        )
            ->facebook()
            ->twitter()
            ->linkedin()
            ->telegram()
            ->whatsapp()
            ->reddit();

        // Trả về view chi tiết bài blog, sử dụng theme hiện tại
        return view(Helper::theme() . 'pages.blog_details')->with($data);
    }

    // Phương thức contactSend: Xử lý gửi thông tin liên hệ từ người dùng
    public function contactSend(Request $request)
    {
        // Xác thực dữ liệu yêu cầu từ người dùng
        $request->validate([
            'name' => 'required', // Tên phải có
            'email' => 'required|email', // Email phải có và đúng định dạng
            'subject' => 'required', // Chủ đề phải có
            'message' => 'required' // Tin nhắn phải có
        ]);

        // Tạo mảng dữ liệu để gửi email
        $data = [
            'subject' => $request->subject,
            'message' => $request->message
        ];

        // Gửi email thông qua helper
        Helper::commonMail($data);

        // Quay lại trang trước và thông báo gửi thành công
        return back()->with('success', 'Contact With us successfully');
    }

    // Phương thức subscribe: Xử lý đăng ký nhận bản tin từ người dùng
    public function subscribe(Request $request)
    {
        // Xác thực email từ người dùng
        $request->validate([
            'email' => 'required|email|unique:subscribers', // Email phải có và chưa tồn tại trong bảng subscribers
        ]);

        // Lưu thông tin email vào bảng subscribers
        Subscriber::create([
            'email' => $request->email
        ]);

        // Trả về phản hồi JSON cho biết đăng ký thành công
        return response()->json(['success' => true]);
    }

    // Phương thức linksDetails: Hiển thị chi tiết liên kết
    public function linksDetails($id)
    {
        // Lấy chi tiết nội dung theo ID
        $details = Content::findOrFail($id);

        // Đặt tiêu đề trang là tiêu đề của nội dung
        $data['title'] = $details->content->page_title;

        // Trả về view chi tiết liên kết
        $data['details'] = $details;

        return view(Helper::theme(). 'link_details')->with($data);
    }
}
