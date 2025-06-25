<?php

namespace App\Http\Controllers\Backend; // Định nghĩa namespace cho controller, giúp phân loại mã nguồn

// Import các lớp và helper cần thiết
use App\Helpers\Helper\Helper; // Lớp Helper chứa các hàm tiện ích, ví dụ: phân trang
use App\Http\Controllers\Controller; // Lớp Controller cơ bản của Laravel
use App\Http\Requests\SignalRequest; // Lớp Request dùng để xác thực dữ liệu cho Signal
use App\Models\CurrencyPair; // Lớp CurrencyPair, sử dụng để làm việc với các cặp tiền tệ
use App\Models\Market; // Lớp Market, sử dụng để làm việc với thị trường
use App\Models\Plan; // Lớp Plan, sử dụng để làm việc với kế hoạch
use App\Models\Signal; // Lớp Signal, sử dụng để làm việc với tín hiệu (signals)
use App\Models\TimeFrame; // Lớp TimeFrame, sử dụng để làm việc với khung thời gian
use Illuminate\Http\Request; // Lớp Request của Laravel để nhận dữ liệu từ client
use App\Services\SignalService; // Lớp Service dùng để xử lý các nghiệp vụ liên quan đến Signal

class SignalController extends Controller
{
    // Khai báo thuộc tính chứa đối tượng SignalService
    protected $signal;

    // Constructor: Hàm khởi tạo SignalController với đối tượng SignalService
    public function __construct(SignalService $signal)
    {
        $this->signal = $signal; // Gán đối tượng SignalService vào thuộc tính $signal
    }

    // Hàm hiển thị trang quản lý tín hiệu
    public function index(Request $request)
    {
        // Đặt tiêu đề trang
        $data['title'] = 'Manage Signals';

        // Lấy danh sách tín hiệu theo các điều kiện tìm kiếm và lọc, sắp xếp theo thứ tự mới nhất
        $data['signals'] = Signal::when($request->type, function ($q) use ($request) {
            // Nếu có tham số 'type', lọc tín hiệu theo trạng thái (draft hoặc published)
            $q->where('is_published', ($request->type === 'draft' ? 0 : 1));
        })
        ->whereHas('plans') // Lọc tín hiệu có kế hoạch
        ->whereHas('pair') // Lọc tín hiệu có cặp tiền tệ
        ->whereHas('time') // Lọc tín hiệu có khung thời gian
        ->whereHas('market') // Lọc tín hiệu có thị trường
        ->search($request->search) // Tìm kiếm theo từ khóa
        ->latest() // Sắp xếp theo thời gian mới nhất
        ->with('plans', 'pair', 'time', 'market') // Lấy thông tin liên quan đến các kế hoạch, cặp tiền tệ, khung thời gian và thị trường
        ->paginate(Helper::pagination()); // Phân trang, sử dụng hàm pagination từ Helper

        // Trả về view backend.signal.index với dữ liệu đã xử lý
        return view('backend.signal.index')->with($data);
    }

    // Hàm hiển thị form tạo tín hiệu mới
    public function create()
    {
        // Đặt tiêu đề trang
        $data['title'] = 'Create Signal';

        // Lấy danh sách các kế hoạch, cặp tiền tệ, khung thời gian và thị trường đã được kích hoạt
        $data['plans'] = Plan::whereStatus(true)->get();
        $data['pairs'] = CurrencyPair::whereStatus(true)->get();
        $data['times'] = TimeFrame::whereStatus(true)->get();
        $data['markets'] = Market::whereStatus(true)->get();

        // Trả về view backend.signal.create với dữ liệu đã xử lý
        return view('backend.signal.create')->with($data);
    }

    // Hàm xử lý việc lưu tín hiệu mới
    public function store(SignalRequest $request)
    {
        // Gọi phương thức create từ SignalService để lưu tín hiệu mới
        $isSuccess = $this->signal->create($request);

        // Nếu tín hiệu được tạo thành công, quay lại trang danh sách tín hiệu với thông báo thành công
        if ($isSuccess['type'] === 'success')
            return redirect()->route('admin.signals.index')->with('success', $isSuccess['message']);
    }

    // Hàm hiển thị form chỉnh sửa tín hiệu
    public function edit($id)
    {
        // Đặt tiêu đề trang
        $data['title'] = 'Edit Signal';

        // Lấy tín hiệu theo ID
        $data['signal'] = Signal::findOrFail($id);

        // Lấy danh sách các kế hoạch, cặp tiền tệ, khung thời gian và thị trường đã được kích hoạt
        $data['plans'] = Plan::whereStatus(true)->get();
        $data['pairs'] = CurrencyPair::whereStatus(true)->get();
        $data['times'] = TimeFrame::whereStatus(true)->get();
        $data['markets'] = Market::whereStatus(true)->get();

        // Trả về view backend.signal.edit với dữ liệu đã xử lý
        return view('backend.signal.edit')->with($data);
    }

    // Hàm xử lý việc cập nhật tín hiệu
    public function update(SignalRequest $request, $id)
    {
        // Gọi phương thức update từ SignalService để cập nhật tín hiệu
        $isSuccess = $this->signal->update($request, $id);

        // Nếu có lỗi trong quá trình cập nhật, quay lại trang trước với thông báo lỗi
        if ($isSuccess['type'] === 'error') {
            return redirect()->back()->with('error', $isSuccess['message']);
        }

        // Nếu cập nhật thành công, quay lại trang trước với thông báo thành công
        return redirect()->back()->with('success', $isSuccess['message']);
    }

    // Hàm xử lý việc xóa tín hiệu
    public function destroy($id)
    {
        // Gọi phương thức destroy từ SignalService để xóa tín hiệu
        $isSuccess = $this->signal->destroy($id);

        // Nếu có lỗi trong quá trình xóa, quay lại trang trước với thông báo lỗi
        if ($isSuccess['type'] === 'error') {
            return redirect()->back()->with('error', $isSuccess['message']);
        }

        // Nếu xóa thành công, quay lại trang trước với thông báo thành công
        return redirect()->back()->with('success', $isSuccess['message']);
    }

    // Hàm gửi tín hiệu cho người dùng
    public function sent($id)
    {
        // Lấy tín hiệu theo ID
        $signal = Signal::findOrFail($id);

        // Đánh dấu tín hiệu là đã công khai (published)
        $signal->is_published = 1;

        // Lưu tín hiệu đã thay đổi
        $signal->save();

        // Gọi phương thức gửi tín hiệu cho người dùng từ SignalService
        $this->signal->sendSignalToUser($signal);

        // Quay lại trang trước với thông báo thành công
        return redirect()->back()->with('success', 'Successfully sent to user');
    }
}
