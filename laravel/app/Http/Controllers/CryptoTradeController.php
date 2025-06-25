<?php

// Khai báo namespace cho controller
namespace App\Http\Controllers;

// Nhập các lớp và helper cần thiết
use App\Helpers\Helper\Helper; // Helper cung cấp các hàm tiện ích (phân trang, cấu hình, định dạng, v.v.)
use App\Models\Trade; // Model đại diện cho bảng trades (giao dịch trade)
use App\Models\Transaction; // Model đại diện cho bảng transactions (giao dịch)
use Carbon\Carbon; // Thư viện xử lý ngày giờ
use Illuminate\Http\Request; // Class xử lý request HTTP
use Illuminate\Support\Str; // Thư viện hỗ trợ xử lý chuỗi

// Class CryptoTradeController kế thừa từ Controller
class CryptoTradeController extends Controller
{
    // Phương thức index: Hiển thị danh sách giao dịch trade của người dùng
    public function index(Request $request)
    {
        // Đặt tiêu đề trang
        $data['title'] = 'Trade';

        // Lấy danh sách giao dịch trade của người dùng hiện tại, lọc theo mã giao dịch (ref) hoặc ngày nếu có
        $data['trades'] = Trade::when($request->trx, function ($item) use ($request) {
            $item->where('ref', $request->trx); // Lọc theo mã giao dịch
        })->when($request->date, function ($item) use ($request) {
            $item->whereDate('trade_opens_at', $request->date); // Lọc theo ngày mở giao dịch
        })->where('user_id', auth()->id())->orderBy('id', 'desc')->paginate(Helper::pagination()); // Lọc theo user_id, sắp xếp giảm dần theo ID và phân trang

        // Trả về view giao dịch của người dùng
        return view(Helper::theme() . 'user.trading')->with($data);
    }

    // Phương thức latestTicker: Lấy dữ liệu giá tiền điện tử theo thời gian thực
    public function latestTicker(Request $request)
    {
        // Lấy cấu hình từ Helper
        $general = Helper::config();

        // Khởi tạo cURL để gọi API CryptoCompare
        $curl = curl_init();

        // Thiết lập các tùy chọn cho yêu cầu cURL
        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://min-api.cryptocompare.com/data/v2/histominute?fsym={$request->currency}&tsym=USD&limit=40&api_key=" . $general->crypto_api, // URL API với mã tiền tệ, USD và giới hạn 40 điểm dữ liệu
            CURLOPT_RETURNTRANSFER => true, // Trả về dữ liệu thay vì in trực tiếp
            CURLOPT_ENCODING => '', // Không sử dụng mã hóa đặc biệt
            CURLOPT_MAXREDIRS => 10, // Giới hạn số lần chuyển hướng tối đa
            CURLOPT_TIMEOUT => 0, // Không giới hạn thời gian chờ
            CURLOPT_FOLLOWLOCATION => true, // Tự động theo dõi chuyển hướng
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1, // Sử dụng HTTP phiên bản 1.1
            CURLOPT_CUSTOMREQUEST => 'GET', // Phương thức GET
        ));

        // Thực thi yêu cầu cURL
        $response = curl_exec($curl);

        // Giải mã dữ liệu JSON từ phản hồi
        $result = json_decode($response);

        // Lấy dữ liệu lịch sử giá (OHLC: Open, High, Low, Close)
        $hvoc = $result->Data->Data;

        // Chuẩn bị dữ liệu cho biểu đồ
        $chartData = [];
        foreach ($hvoc as $key => $value) {
            $chartData[$key] = [
                'x' => $value->time, // Thời gian
                'y' => [$value->open, $value->high, $value->low, $value->close] // Giá mở, cao nhất, thấp nhất, đóng cửa
            ];
        }

        // Đóng kết nối cURL
        curl_close($curl);

        // Trả về dữ liệu biểu đồ dưới dạng JSON
        return response()->json($chartData);
    }

    // Phương thức currentPrice: Lấy giá hiện tại của một loại tiền điện tử
    public function currentPrice(Request $request)
    {
        // Lấy cấu hình từ Helper
        $general = Helper::config();

        // Lấy mã tiền tệ từ request
        $currency = $request->currency;

        // Gọi API CryptoCompare để lấy giá hiện tại
        $data = json_decode(file_get_contents("https://min-api.cryptocompare.com/data/price?fsym={$request->currency}&tsyms=USD&api_key=" . $general->crypto_api), true);

        // Lấy giá USD từ dữ liệu
        $result = reset($data);

        // Trả về giá dưới dạng JSON
        return response()->json($result);
    }

    // Phương thức trades: Hiển thị danh sách giao dịch của người dùng
    public function trades()
    {
        // Lấy danh sách giao dịch của người dùng hiện tại với phân trang
        $data['trades'] = Trade::where('user_id', auth()->id())->paginate(Helper::pagination());

        // Đặt tiêu đề trang
        $data['title'] = 'Trades List';

        // Trả về view danh sách giao dịch
        return view(Helper::theme() . 'user.trade_list')->with($data);
    }

    // Phương thức openTrade: Mở một giao dịch trade mới
    public function openTrade(Request $request)
    {
        // Xác thực dữ liệu đầu vào
        $request->validate([
            "trade_cur" => "required", // Mã tiền tệ bắt buộc
            "trade_price" => "required", // Giá giao dịch bắt buộc
            "type" => "required|in:buy,sell", // Loại giao dịch phải là buy hoặc sell
            "duration" => "required|gt:0" // Thời gian giao dịch phải lớn hơn 0
        ]);

        // Lấy thông tin người dùng hiện tại
        $user = auth()->user();

        // Kiểm tra giới hạn số lượng giao dịch mỗi ngày
        if ($user->trades->count() >= Helper::config()->trade_limit) {
            return redirect()->back()->with('error', 'Per Day Trading Limit expired');
        }

        // Kiểm tra xem người dùng đã đăng ký gói dịch vụ chưa
        if ($user->payments->count() <= 0) {
            return redirect()->back()->with('error', 'You need to subscribe a plan to trade');
        }

        // Kiểm tra số dư tối thiểu để giao dịch
        if ($user->balance < Helper::config()->min_trade_balance) {
            return redirect()->back()->with('error', 'You need minimum of ' . Helper::formatter(Helper::config()->min_trade_balance) . ' To Trade');
        }

        // Tạo mã giao dịch ngẫu nhiên
        $ref = Str::random(16);

        // Tạo giao dịch trade mới
        Trade::create([
            'ref' => $ref, // Mã giao dịch
            'user_id' => auth()->id(), // ID người dùng
            'currency' => $request->trade_cur, // Mã tiền tệ
            'current_price' => $request->trade_price, // Giá hiện tại
            'trade_type' => $request->type, // Loại giao dịch (buy/sell)
            'duration' => $request->duration, // Thời gian giao dịch (phút)
            'trade_stop_at' => now()->addMinutes($request->duration), // Thời gian kết thúc giao dịch
            'trade_opens_at' => now() // Thời gian mở giao dịch
        ]);

        // Chuyển hướng về trang trước với thông báo thành công
        return redirect()->back()->with('success', 'Trade Open Successfully');
    }

    // Phương thức tradeClose: Đóng các giao dịch trade đang mở
    public function tradeClose()
    {
        // Lấy cấu hình từ Helper
        $config = Helper::config();

        // Lấy danh sách giao dịch đang mở (status = 0) của người dùng
        $trades = Trade::where('user_id', auth()->id())->where('status', 0)->get();

        // Duyệt qua từng giao dịch
        foreach ($trades as $trade) {
            // Kiểm tra nếu giao dịch đã đến thời gian kết thúc
            if ($trade->trade_stop_at->lte(now())) {
                // Lấy giá hiện tại từ API CryptoCompare
                $data = json_decode(file_get_contents("https://min-api.cryptocompare.com/data/price?fsym={$trade->currency}&tsyms=USD&api_key=" . $config->crypto_api), true);
                $currentPrice = reset($data);

                // Nếu giá hiện tại cao hơn giá mở giao dịch
                if ($currentPrice > $trade->current_price) {
                    // Tính toán lợi nhuận
                    $amount = $currentPrice - $trade->current_price;
                    $charge = ($config->trade_charge / 100) * $amount; // Phí giao dịch
                    $userAmount = $amount - $charge; // Số tiền người dùng nhận được
                    $type = '+'; // Loại giao dịch: lợi nhuận

                    // Cập nhật thông tin giao dịch
                    $trade->profit_type = $type;
                    $trade->profit_amount = $amount;
                    $trade->charge = $charge;
                    $trade->status = 1; // Đánh dấu giao dịch đã đóng

                    // Cập nhật số dư người dùng
                    $trade->user->balance += $userAmount;
                    $trade->user->save();
                } else {
                    // Tính toán lỗ
                    $amount = $trade->current_price - $currentPrice;
                    $charge = 0; // Không tính phí khi lỗ
                    $userAmount = $amount; // Số tiền người dùng mất
                    $type = '-'; // Loại giao dịch: lỗ

                    // Cập nhật thông tin giao dịch
                    $trade->profit_type = $type;
                    $trade->loss_amount = $amount;
                    $trade->charge = 0;
                    $trade->status = 1; // Đánh dấu giao dịch đã đóng

                    // Cập nhật số dư người dùng
                    $trade->user->balance -= $userAmount;
                    $trade->user->save();
                }

                // Lưu thông tin giao dịch
                $trade->save();

                // Tạo bản ghi giao dịch trong bảng transactions
                Transaction::create([
                    'trx' => $trade->ref, // Mã giao dịch
                    'amount' => $amount, // Số tiền lợi nhuận/lỗ
                    'details' => 'Trade Return', // Chi tiết giao dịch
                    'charge' => $charge, // Phí giao dịch
                    'type' => $type, // Loại giao dịch (+/-)
                    'user_id' => $trade->user->id // ID người dùng
                ]);
            }
        }
    }

    // Phương thức tradingInterest: Xử lý lãi/lỗ cho các giao dịch trade đang mở
    public function tradingInterest()
    {
        // Lấy cấu hình từ Helper
        $config = Helper::config();

        // Lấy tất cả giao dịch đang mở (status = 0)
        $trades = Trade::where('status', 0)->get();

        // Duyệt qua từng giao dịch
        foreach ($trades as $trade) {
            // Kiểm tra nếu giao dịch đã đến thời gian kết thúc
            if ($trade->trade_stop_at->lte(now())) {
                // Lấy giá hiện tại từ API CryptoCompare
                $data = json_decode(file_get_contents("https://min-api.cryptocompare.com/data/price?fsym={$trade->currency}&tsyms=USD&api_key=" . $config->crypto_api), true);
                $currentPrice = reset($data);

                // Nếu giá hiện tại cao hơn giá mở giao dịch
                if ($currentPrice > $trade->current_price) {
                    // Tính toán lợi nhuận
                    $amount = $currentPrice - $trade->current_price;
                    $charge = ($config->trade_charge / 100) * $amount; // Phí giao dịch
                    $userAmount = $amount - $charge; // Số tiền người dùng nhận được
                    $type = '+'; // Loại giao dịch: lợi nhuận

                    // Cập nhật thông tin giao dịch
                    $trade->profit_type = $type;
                    $trade->profit_amount = $amount;
                    $trade->charge = $charge;
                    $trade->status = 1; // Đánh dấu giao dịch đã đóng

                    // Cập nhật số dư người dùng
                    $trade->user->balance += $userAmount;
                    $trade->user->save();
                } else {
                    // Tính toán lỗ
                    $amount = $trade->current_price - $currentPrice;
                    $charge = 0; // Không tính phí khi lỗ
                    $userAmount = $amount; // Số tiền người dùng mất
                    $type = '-'; // Loại giao dịch: lỗ

                    // Cập nhật thông tin giao dịch
                    $trade->profit_type = $type;
                    $trade->loss_amount = $amount;
                    $trade->charge = 0;
                    $trade->status = 1; // Đánh dấu giao dịch đã đóng

                    // Cập nhật số dư người dùng
                    $trade->user->balance -= $userAmount;
                    $trade->user->save();
                }

                // Lưu thông tin giao dịch
                $trade->save();

                // Tạo bản ghi giao dịch trong bảng transactions
                Transaction::create([
                    'trx' => $trade->ref, // Mã giao dịch
                    'amount' => $amount, // Số tiền lợi nhuận/lỗ
                    'details' => 'Trade Return', // Chi tiết giao dịch
                    'charge' => $charge, // Phí giao dịch
                    'type' => $type, // Loại giao dịch (+/-)
                    'user_id' => $trade->user->id // ID người dùng
                ]);
            }
        }
    }
}