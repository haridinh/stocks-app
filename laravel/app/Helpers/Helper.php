<?php

// Khai báo thư viện và namespace
namespace App\Helpers\Helper;

use App\Mail\BulkMail;
use App\Mail\TemplateMail;
use App\Models\Admin;
use App\Models\Configuration;
use App\Models\Content;
use App\Models\FrontendMedia;
use App\Models\Language;
use App\Models\Page;
use App\Models\PlanSubscription;
use App\Models\Referral;
use App\Models\ReferralCommission;
use App\Models\Template;
use App\Models\Ticket;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Withdraw;
use App\Notifications\DepositNotification;
use App\Notifications\PlanSubscriptionNotification;
use App\Utility\Config;
use Image;
use DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;
use Throwable;

// Khai báo class
class Helper
{

    const APP_VERSION = '5.0';

    public static function isInstalled()
    {
        if (!file_exists(storage_path('installed'))) {
            return true;
        }

        return false;
    /**
     * Phương thức isInstalled() kiểm tra sự tồn tại của tệp installed trong thư mục lưu trữ (storage). Nếu tệp này không có, ứng dụng được cho là chưa được cài đặt và trả về true. Nếu tệp tồn tại, ứng dụng đã được cài đặt và trả về false.
     */
    }


    public static function languageSelection($code)
    {
        $default = Language::where('status', 0)->first()->code;

        if (session()->has('locale')) {
            if (session('locale') == $code) {
                return 'selected';
            }
        } else {
            if ($code == $default) {
                return 'selected';
            }
        }
    /**
     * Phương thức languageSelection($code) dùng để xác định xem mã ngôn ngữ $code có được chọn hay không, dựa trên hai trường hợp:
         * Nếu người dùng đã chọn ngôn ngữ và lưu trong session, nó sẽ so sánh với ngôn ngữ đã lưu trong session và trả về 'selected' nếu trùng.
         * Nếu người dùng chưa chọn ngôn ngữ, phương thức sẽ kiểm tra xem ngôn ngữ mặc định có khớp với $code không và trả về 'selected' nếu trùng.
    */
    }

    public static function config()
    {
        return Configuration::first();
    /**
     * Phương thức config() sẽ trả về bản ghi đầu tiên trong bảng Configuration. Thường thì bảng này sẽ chứa các thông tin cấu hình chung của ứng dụng, ví dụ như các tham số cấu hình như site_name, site_logo, app_version, v.v.
    */
    }

    public static function imagePath($folder, $default = false)
    {
        $general = Helper::config();

        if ($default) {
            return 'asset/images/' . $folder;
        }

        return 'asset/frontend/' . $general->theme . '/images/' . $folder;
    /**
     * Phương thức imagePath() sẽ tạo và trả về đường dẫn đến hình ảnh tùy thuộc vào hai yếu tố:
     	* Nếu tham số $default được đặt là true, phương thức trả về đường dẫn đến thư mục hình ảnh chung (asset/images/{folder}).
     	* Nếu tham số $default là false, phương thức trả về đường dẫn đến thư mục hình ảnh theo chủ đề của ứng dụng (asset/frontend/{theme}/images/{folder}).
    */
    }

    public static function fetchImage($folder, $filename, $default = false)
    {
        $general = Helper::config();
        if ($default == true) {
            if (file_exists(Helper::imagePath($folder, $default) . '/' . $filename) && $filename != null) {
                return asset('asset/images/' . $folder . '/' . $filename);
            }
            return asset('asset/images/placeholder.png');
        }
        if (file_exists(Helper::imagePath($folder) . '/' . $filename) && $filename != null) {
            return asset('asset/frontend/' . $general->theme . '/images/' . $folder . '/' . $filename);
        }
        return asset('asset/images/placeholder.png');
    /**
     * Phương thức fetchImage kiểm tra sự tồn tại của một hình ảnh trong hệ thống và trả về đường dẫn tới hình ảnh đó. Nếu hình ảnh không tồn tại, nó sẽ trả về một hình ảnh mặc định (placeholder.png).
     	* Nếu tham số $default là true, phương thức sẽ kiểm tra hình ảnh trong thư mục mặc định (asset/images/{folder}/{filename}).
     	* Nếu tham số $default là false (hoặc không được truyền vào), phương thức sẽ kiểm tra hình ảnh trong thư mục theo chủ đề của ứng dụng (asset/frontend/{theme}/images/{folder}/{filename}).
     	* Nếu không tìm thấy hình ảnh trong bất kỳ trường hợp nào, nó sẽ trả về hình ảnh thay thế (placeholder.png).
     */
    }

    public static function cssLib($folder, $filename)
    {
        $template = self::config()->theme;

        if ($folder == 'backend') {
            return asset("asset/{$folder}/css/{$filename}");
        }

        return asset("asset/{$folder}/{$template}/css/{$filename}");
    /**
     * Phương thức cssLib() giúp tạo đường dẫn tới tệp CSS trong ứng dụng, tùy thuộc vào thư mục và chủ đề hiện tại:
    	* Nếu tham số $folder là 'backend', phương thức sẽ trả về đường dẫn đến tệp CSS trong thư mục asset/backend/css/{filename}.
     	* Nếu tham số $folder là bất kỳ giá trị nào khác (như 'frontend'), phương thức sẽ tạo đường dẫn tới tệp CSS trong thư mục của chủ đề hiện tại, ví dụ: asset/frontend/{theme}/css/{filename}.
    */
    }

    public static function jsLib($folder, $filename)
    {
        $template = self::config()->theme;

        if ($folder == 'backend') {
            return asset("asset/{$folder}/js/{$filename}");
        }

        return asset("asset/{$folder}/{$template}/js/{$filename}");
    /**
     * Phương thức jsLib() giúp tạo đường dẫn tới tệp JavaScript trong ứng dụng, tùy thuộc vào thư mục và chủ đề hiện tại:
     	* Nếu tham số $folder là 'backend', phương thức sẽ trả về đường dẫn đến tệp JavaScript trong thư mục asset/backend/js/{filename}.
     	* Nếu tham số $folder là bất kỳ giá trị nào khác (chẳng hạn 'frontend'), phương thức sẽ tạo đường dẫn tới tệp JavaScript trong thư mục của chủ đề hiện tại, ví dụ: asset/frontend/{theme}/js/{filename}.
    */
    }

    public static function verificationCode($length)
    {
        if ($length == 0) {
            return 0;
        }

        $min = pow(10, $length - 1);
        $max = 0;
        while ($length > 0 && $length--) {
            $max = ($max * 10) + 9;
        }
        return random_int($min, $max);
    /**
     * Phương thức verificationCode($length) tạo ra một mã xác minh ngẫu nhiên có độ dài bằng $length. Độ dài của mã xác minh này được xác định bởi tham số $length:
     	* Nếu $length là 0, phương thức trả về 0.
     	* Phương thức sẽ tính giá trị tối thiểu ($min) và tối đa ($max) của số ngẫu nhiên tùy thuộc vào độ dài.
     	* Cuối cùng, hàm random_int() sẽ tạo ra một số ngẫu nhiên trong khoảng từ $min đến $max và trả về kết quả.
    */
    }

    public static function fireMail($data, $template)
    {
        $html = $template->template;

        $general = self::config();

        foreach ($data as $key => $value) {
            $html = str_replace("%" . $key . "%", $value, $html);
        }

        if (self::config()->email_method == 'php') {
            $headers = "From: $general->appname <$general->email_sent_from> \r\n";
            $headers .= "Reply-To: $general->appname <$general->email_sent_from> \r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=utf-8\r\n";
            @mail($data['email'], $template->subject, $html, $headers);
        } else {
            try {

                Mail::to($data['email'])->send(
                    new TemplateMail($template->subject, $html)
                );
            } catch (Throwable $exception) {

                return ['type' => 'invalid', 'message' => 'Invalid Email Configuration'];
            }
        }
    /**
     * Phương thức fireMail có thể gửi email sử dụng hai phương thức:
     	* PHP mail function: Nếu cấu hình yêu cầu gửi email qua hàm mail() của PHP, thì email sẽ được gửi với các header được cấu hình sẵn.
     	* Laravel Mail: Nếu cấu hình yêu cầu gửi email qua Laravel, phương thức sẽ sử dụng facade Mail của Laravel để gửi email thông qua một đối tượng TemplateMail (lớp Mailable).
     * Phương thức này có thể gửi email với nội dung động bằng cách thay thế các biến trong template với dữ liệu thực tế từ mảng $data. Điều này có ích khi bạn cần gửi email theo mẫu (template) nhưng nội dung sẽ thay đổi theo từng trường hợp (ví dụ: tên người nhận, email, v.v.).
    */
    }

    public static function commonMail($data)
    {

        $general = self::config();

        if (!isset($data['email'])) {
            $data['email'] = $general->email_sent_from;
        }

        if (self::config()->email_method == 'php') {
            $headers = "From: $general->appname <$general->email_sent_from> \r\n";
            $headers .= "Reply-To: $general->appname <$general->email_sent_from> \r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=utf-8\r\n";
            @mail($data['email'], $data['subject'], $data['message'], $headers);
        } else {
            try {

                Mail::to($data['email'])->send(
                    new BulkMail($data['subject'], $data['message'])
                );
            } catch (Throwable $exception) {
                Log::error($exception);

                return ['type' => 'error', 'message' => 'Invalid Email Configuration'];
            }
        }
    /**
     * Phương thức commonMail thực hiện việc gửi email dựa trên cấu hình của ứng dụng. Nó hỗ trợ hai phương thức gửi email:
     	* PHP mail function ($general->email_method == 'php'): Nếu cấu hình yêu cầu sử dụng hàm mail() của PHP, phương thức này sẽ gửi email bằng cách sử dụng các header cần thiết (From, Reply-To, Content-Type) và nội dung email (HTML).
     	* Laravel Mail (Nếu không phải là PHP mail): Nếu cấu hình yêu cầu sử dụng hệ thống gửi email của Laravel, phương thức sẽ tạo đối tượng BulkMail và sử dụng Laravel's Mail facade để gửi email. Trong trường hợp có lỗi, thông báo lỗi sẽ được ghi lại và trả về.
    */
    }

    // truy xuất giá trị cấu hình liên quan đến phân trang (pagination)
    public static function pagination()
    {
        return self::config()->pagination;
    }

    public static function formatter($number)
    {
        $config = self::config()->decimal_precision;

        return number_format($number, $config) . ' ' . self::config()->currency;
    /**
     * Phương thức formatter thực hiện các bước sau:
     	* Lấy cấu hình: Đầu tiên, phương thức lấy cấu hình về độ chính xác của số thập phân (decimal_precision) và đơn vị tiền tệ (currency).
     	* Định dạng số: Sau đó, sử dụng hàm number_format() để định dạng số theo độ chính xác đã được chỉ định trong cấu hình.
     	* Trả về kết quả: Cuối cùng, phương thức trả về chuỗi kết quả với số đã được định dạng, kèm theo đơn vị tiền tệ.
    */
    }


    public static function formatOnlyNumber($number)
    {
        $config = self::config()->decimal_precision;

        return number_format($number, $config);
    /**
     * Phương thức formatOnlyNumber thực hiện các bước sau:
     	* Lấy cấu hình: Đầu tiên, phương thức lấy cấu hình về độ chính xác của số thập phân (decimal_precision).
     	* Định dạng số: Sau đó, sử dụng hàm number_format() để định dạng số theo độ chính xác đã được chỉ định trong cấu hình.
     	* Trả về kết quả: Cuối cùng, phương thức trả về chuỗi kết quả với số đã được định dạng.
    */
    }

    public static function languages()
    {
        return Language::latest()->get();
    }

    public static function pages()
    {
        return Page::where('status', 1)->where('name', '!=', 'home')->get();
    }

    public static function notifications()
    {
        return auth()->guard('admin')->user()->unreadNotifications()->latest()->get();
    }

    public static function sidebarData()
    {
        $data['deactiveUser'] = User::where('status', 0)->count();
        $data['emailUnverified'] = User::where('is_email_verified', 0)->count();
        $data['smsUnverified'] = User::where('is_sms_verified', 0)->count();
        $data['kycUnverified'] = User::whereIn('is_kyc_verified', [0, 2])->count();
        $data['kyc_req'] = User::where('is_kyc_verified', 2)->where('kyc_information', '!=', null)->count();

        $data['pendingTicket'] = Ticket::where('status', 2)->count();

        $data['pendingWithdraw'] = Withdraw::where('status', 0)->count();

        return $data;
    }

    public static function theme()
    {
        return 'frontend.' . Configuration::first()->theme . '.';
    }


    public static function makeDir($path)
    {
        if (file_exists($path)) return true;
        return mkdir($path, 0775, true);
    }

    public static function removeFile($path)
    {
        return file_exists($path) && is_file($path) ? unlink($path) : false;
    }

    public static function frontendFormatter($key)
    {
        return ucwords(str_replace('_', ' ', $key));
    }

	public static function filePath($folder_name, $default = false)
	{
	    $general = self::config();

	    // Nếu tham số $default là true, trả về đường dẫn hình ảnh mặc định
	    if ($default) {
	        return 'asset/images/' . $folder_name;
	    }

	    // Nếu không, trả về đường dẫn hình ảnh theo chủ đề hiện tại của ứng dụng
	    return 'asset/frontend/' . $general->theme . '/images/' . $folder_name;
	}

	public static function saveImage($image, $directory, $removeFile = '')
	{
	    // Tạo thư mục nếu chưa tồn tại
	    $path = self::makeDir($directory);

	    // Nếu có tệp cũ cần xóa, gọi phương thức xóa tệp đó
	    if (!empty($removeFile)) {
	        self::removeFile($directory . '/' . $removeFile);
	    }

	    // Tạo tên tệp mới (sử dụng uniqid và time để đảm bảo tên tệp duy nhất)
	    $filename = uniqid() . time() . '.' . $image->getClientOriginalExtension();

	    // Kiểm tra định dạng tệp là GIF, nếu là GIF sử dụng hàm copy để sao chép trực tiếp
	    if ($image->getClientOriginalExtension() == 'gif') {
	        copy($image->getRealPath(), $directory . '/' . $filename);
	    } else {
	        // Nếu không phải GIF, sử dụng thư viện Image để xử lý và lưu ảnh
	        $image = Image::make($image);
	        $image->save($directory . '/' . $filename);
	    }

	    // Trả về tên tệp đã lưu
	    return $filename;
	}

	public static function getFile($folder_name, $filename, $default = false)
	{
	    // Lấy cấu hình chung của ứng dụng
	    $general = self::config();

	    // Kiểm tra nếu biến $default được bật
	    if ($default) {
	        // Kiểm tra nếu tệp tin tồn tại trong thư mục mặc định
	        if (file_exists(self::filePath($folder_name, $default) . '/' . $filename) && $filename != null) {
	            return asset('asset/images/' . $folder_name . '/' . $filename);
	        }
	    }

	    // Kiểm tra nếu tệp tin tồn tại trong thư mục theo theme của ứng dụng
	    if (file_exists(self::filePath($folder_name) . '/' . $filename) && $filename != null) {
	        return asset('asset/frontend/' . $general->theme . '/images/' . $folder_name . '/' . $filename);
	    }

	    // Nếu tệp tin không tồn tại, trả về tệp hình ảnh mặc định (placeholder)
	    return asset('asset/images/placeholder.png');
	}

    public static function sectionConfig()
    {
        return Config::sectionsSelectable();
    }

	public static function activeMenu($route)
	{
	    // Kiểm tra nếu $route là một mảng
	    if (is_array($route)) {
	        // Nếu URL hiện tại có trong mảng $route, trả về 'active'
	        if (in_array(url()->current(), $route)) {
	            return 'active';
	        }
	    }

	    // Nếu $route là một chuỗi và khớp với URL hiện tại, trả về 'active'
	    if ($route == url()->current()) {
	        return 'active';
	    }
	}

	public static function builder($section, $collection = false)
	{
	    // Kiểm tra nếu tham số $collection được cung cấp và có giá trị true
	    if ($collection) {
	        // Truy vấn cơ sở dữ liệu và trả về một tập hợp các bản ghi 'iteratable' (có thể lặp lại)
	        return Content::where('type', 'iteratable')
	                      ->where('theme', self::config()->theme)
	                      ->where('name', $section)
	                      ->get();
	    }

	    // Nếu không phải là collection, tìm và trả về bản ghi đầu tiên có kiểu 'non_iteratable' (không thể lặp lại)
	    return Content::where('type', 'non_iteratable')
	                  ->where('theme', self::config()->theme)
	                  ->where('name', $section)
	                  ->first();
	}

	public static function media($section, $key,  $type = false, $id = null)
	{
	    // Kiểm tra xem tham số 'type' có được cung cấp hay không
	    if ($type) {
	        // Truy vấn cơ sở dữ liệu để lấy phương tiện có kiểu 'iteratable' và có content_id bằng với id đã cho
	        $media = FrontendMedia::where('content_id', $id)->where('section_name', $section)->where('type', 'iteratable')->first();

	        // Nếu tìm thấy phương tiện, trả về tệp tương ứng từ phương thức getFile()
	        if ($media) {
	            return self::getFile($section, optional($media->media)->$key);
	        } else {
	            // Nếu không tìm thấy, trả về tệp mặc định (empty string)
	            return self::getFile($section, '');
	        }
	    }

	    // Nếu không có tham số 'type' (hoặc type = false), tìm kiếm phương tiện với kiểu 'non_iteratable'
	    $media = FrontendMedia::where('section_name', $section)->where('type', 'non_iteratable')->first();

	    // Trả về tệp phương tiện không lặp lại (non-iterable)
	    return self::getFile($section, optional($media->media)->$key);
	}

	public static function colorText($haystack, $needle)
	{
	    // Bọc chuỗi cần tìm (needle) trong một thẻ <span>
	    $replace = "<span>{$needle}</span>";

	    // Thay thế tất cả các xuất hiện của needle trong haystack bằng thẻ <span> chứa needle
	    return str_replace($needle, $replace, $haystack);
	}

	public static function setEnv(array $values)
	{
	    // Lấy đường dẫn đến tệp .env của ứng dụng
	    $envFile = app()->environmentFilePath();

	    // Đọc toàn bộ nội dung của tệp .env vào một chuỗi
	    $str = file_get_contents($envFile);

	    // Kiểm tra nếu có các giá trị mới cần thay thế trong tệp .env
	    if (count($values) > 0) {
	        // Duyệt qua từng cặp key-value trong mảng $values
	        foreach ($values as $envKey => $envValue) {
	            $str .= "\n"; // Thêm một dòng mới

	            // Tìm vị trí của key trong nội dung tệp .env
	            $keyPosition = strpos($str, "{$envKey}=");
	            $endOfLinePosition = strpos($str, "\n", $keyPosition);
	            $oldLine = substr($str, $keyPosition, $endOfLinePosition - $keyPosition);

	            // Nếu không tìm thấy dòng chứa key, thêm cặp key-value mới vào cuối
	            if (!$keyPosition || !$endOfLinePosition || !$oldLine) {
	                $str .= "{$envKey}={$envValue}\n";
	            } else {
	                // Nếu tìm thấy, thay thế dòng cũ bằng dòng mới chứa key-value mới
	                $str = str_replace($oldLine, "{$envKey}={$envValue}", $str);
	            }
	        }
	    }

	    // Cắt bỏ ký tự xuống dòng thừa ở cuối chuỗi
	    $str = substr($str, 0, -1);

	    // Ghi lại nội dung đã thay đổi vào tệp .env
	    if (!file_put_contents($envFile, $str)) return false;

	    // Trả về true nếu ghi thành công
	    return true;
	}

	public static function singleMenu($routeName)
	{
	    // Lớp CSS mặc định cho mục menu đang được chọn
	    $class = 'active';

	    // Kiểm tra nếu route hiện tại khớp với route được truyền vào
	    if (request()->routeIs($routeName)) {
	        return $class; // Trả về lớp CSS 'active' nếu route khớp
	    }

	    // Nếu route không khớp, trả về chuỗi rỗng (không thêm lớp CSS nào)
	    return '';
	}

	public static function paymentSuccess($deposit, $fee_amount, $transaction)
	{
	    // Lấy cấu hình chung của hệ thống
	    $general = Configuration::first();

	    // Lấy thông tin quản trị viên siêu cấp (super admin)
	    $admin = Admin::where('type', 'super')->first();

	    // Lấy thông tin người dùng hiện tại (đang đăng nhập)
	    $user = auth()->user();

	    // Kiểm tra nếu phiên hiện tại là 'deposit' (nạp tiền)
	    if (session('type') == 'deposit') {
	        // Cộng tiền vào tài khoản người dùng
	        $user->balance = $user->balance + $deposit->amount;

	        // Lưu lại thông tin người dùng sau khi thay đổi số dư
	        $user->save();

	        // Gửi thông báo cho admin về giao dịch nạp tiền (deposit)
	        $admin->notify(new DepositNotification($deposit, 'online', 'deposit'));
	    }

	    // Cập nhật trạng thái của giao dịch deposit thành đã hoàn thành (status = 1)
	    $deposit->status = 1;

	    // Lưu lại thay đổi của giao dịch deposit
	    $deposit->save();

	    // Chuẩn bị dữ liệu đăng ký gói cho người dùng
	    $data = [
	        'plan_id' => $deposit->plan_id,
	        'user_id' => $user->id,
	    ];

	    // Nếu không phải giao dịch nạp tiền (deposit), tiến hành đăng ký gói dịch vụ
	    if (!(session('type') == 'deposit')) {
	        // Tạo một đăng ký gói mới cho người dùng
	        $subscription = self::subscription($data, $deposit);

	        // Gửi thông báo cho admin về đăng ký gói mới của người dùng
	        $admin->notify(new PlanSubscriptionNotification($subscription));

	        // Tính hoa hồng từ hệ thống giới thiệu (referral)
	        self::referMoney(auth()->id(), $deposit->user->refferedBy, 'invest', $deposit->amount);
	    }

	    // Tạo một bản ghi giao dịch mới cho giao dịch thanh toán thành công
	    Transaction::create([
	        'trx' => $deposit->trx,                 // Mã giao dịch (transaction ID)
	        'amount' => $deposit->amount,           // Số tiền giao dịch
	        'details' => 'Payment Successfull',     // Chi tiết giao dịch
	        'charge' => $fee_amount,                // Phí giao dịch
	        'type' => '+',                           // Loại giao dịch (tăng tài khoản)
	        'user_id' => auth()->id()               // ID người dùng thực hiện giao dịch
	    ]);

	    // Lấy mẫu email thông báo về thanh toán thành công
	    $template = Template::where('name', 'payment_successfull')->where('status', 1)->first();

	    // Nếu tìm thấy mẫu email, gửi email cho người dùng
	    if ($template) {
	        self::fireMail([
	            'username' => $deposit->user->username, // Tên người dùng
	            'app_name' => $general->appname,        // Tên ứng dụng
	            'email' => $deposit->user->email,       // Email người dùng
	            'plan' => $deposit->plan->name ?? 'Deposit', // Tên gói hoặc mặc định là 'Deposit'
	            'trx' => $transaction,                  // Mã giao dịch
	            'amount' => $deposit->amount,           // Số tiền thanh toán
	            'currency' => $general->currency,       // Đơn vị tiền tệ
	        ], $template);
	    }
	}

	private static function subscription($data, $deposit)
	{
	    // Lấy thông tin các gói đăng ký của người dùng hiện tại từ cơ sở dữ liệu
	    $subscription = auth()->user()->subscriptions;

	    // Kiểm tra nếu người dùng đã có gói đăng ký
	    if ($subscription) {
	        // Nếu có, cập nhật tất cả các gói của người dùng hiện tại thành 'is_current = 0'
	        DB::table('plan_subscriptions')->where('user_id', auth()->id())->update(['is_current' => 0]);
	    }

	    // Tạo một gói đăng ký mới cho người dùng
	    // Dữ liệu được lấy từ tham số $data và $deposit
	    $id = PlanSubscription::create([
	        'plan_id' => $data['plan_id'],               // ID của gói đăng ký
	        'user_id' => $data['user_id'],               // ID người dùng đăng ký
	        'is_current' => 1,                           // Đánh dấu gói này là gói hiện tại
	        'plan_expired_at' => $deposit->plan_expired_at // Thời gian hết hạn của gói kế hoạch
	    ]);

	    // Trả về ID của gói đăng ký mới tạo
	    return $id;
	}


	public static function referMoney($from, $to, $refferal_type, $amount)
	{
	    // Biến $user_id lưu lại người dùng gửi hoa hồng (from)
	    $user_id = $from;

	    // Lấy cấp độ giới thiệu (Referral) từ cơ sở dữ liệu
	    // Tìm cấp độ giới thiệu dựa trên trạng thái 'active' và loại referral ('interest' hoặc 'invest')
	    $level = Referral::where('status', 1)->where('type', $refferal_type)->first();

	    // Số lượng cấp độ trong chương trình giới thiệu (có thể là 1, 2, 3... cấp)
	    $counter = $level ? count($level->level) : 0;

	    // Lấy cấu hình ứng dụng, ví dụ như tên ứng dụng và tiền tệ
	    $general = Configuration::first();

	    // Duyệt qua các cấp độ để tính hoa hồng cho từng cấp
	    for ($i = 0; $i < $counter; $i++) {

	        // Kiểm tra người nhận hoa hồng (to) có tồn tại hay không
	        if ($to) {

	            // Nếu loại referral là 'interest', hoa hồng được tính trực tiếp theo mức lãi suất
	            // Nếu loại referral là 'invest', hoa hồng tính theo tỷ lệ phần trăm của số tiền đầu tư
	            if ($refferal_type == 'interest') {
	                $commission = $level->commission[$i];
	            } else {
	                $commission = ($level->commission[$i] * $amount) / 100;
	            }

	            // Cập nhật số dư của người nhận hoa hồng
	            $to->balance = $to->balance + $commission;
	            $to->save();

	            // Tạo một giao dịch mới cho người nhận hoa hồng
	            Transaction::create([
	                'trx' => Str::upper(Str::random(16)),  // Mã giao dịch ngẫu nhiên
	                'user_id' => $to->id,                 // ID người nhận hoa hồng
	                'amount' => $commission,              // Số tiền hoa hồng
	                'charge' => 0,                        // Phí giao dịch (ở đây là 0)
	                'details' => 'Refferal Commission from level ' . ($i + 1) . ' user',  // Mô tả giao dịch
	                'type' => '+'                         // Loại giao dịch là cộng (+)
	            ]);

	            // Lưu thông tin hoa hồng trong bảng ReferralCommission
	            ReferralCommission::create([
	                'commission_to' => $to->id,          // ID người nhận hoa hồng
	                'commission_from' => $user_id,       // ID người gửi hoa hồng
	                'amount' => $commission,             // Số tiền hoa hồng
	                'purpouse' => $refferal_type === 'invest' ? 'Return invest commission' : 'Return Interest Commission' // Mục đích của hoa hồng
	            ]);

	            // Lấy mẫu email thông báo về hoa hồng
	            $template = Template::where('name', 'refer_commission')->where('status', 1)->first();

	            // Nếu mẫu email tồn tại, gửi email thông báo
	            if ($template) {
	                self::fireMail([
	                    'username' => $to->username,
	                    'email' => $to->email,
	                    'app_name' => $general->appname,
	                    'refer_user' => User::find($from)->username,  // Tên người giới thiệu
	                    'amount' => $commission,                      // Số tiền hoa hồng
	                    'currency' => $general->currency,             // Tiền tệ của ứng dụng
	                ], $template);
	            }

	            // Cập nhật người gửi hoa hồng (from) cho lần gọi tiếp theo
	            $from = $to->id;
	            // Cập nhật người nhận hoa hồng (to) là người đã giới thiệu (referredBy)
	            $to = $to->refferedBy;
	        }
	    }
	}

	public static function navbarMenus()
	{
	    // Lấy các trang có dropdown, trừ trang 'home', có trạng thái là 'active' (status = 1)
	    $dropdowns = Page::where('name', '!=', 'home') // Không lấy trang 'home'
	                     ->where('is_dropdown', true)  // Chỉ lấy các trang có dropdown
	                     ->where('status', 1)         // Trang phải có trạng thái 'active'
	                     ->orderBy('order', 'ASC')    // Sắp xếp các trang theo thứ tự 'order'
	                     ->get();                     // Lấy tất cả các kết quả thỏa mãn điều kiện

	    // Lấy các trang không có dropdown, trừ trang 'home', có trạng thái là 'active' (status = 1)
	    $nonDropdowns = Page::where('name', '!=', 'home') // Không lấy trang 'home'
	                        ->where('is_dropdown', false) // Chỉ lấy các trang không có dropdown
	                        ->where('status', 1)         // Trang phải có trạng thái 'active'
	                        ->orderBy('order', 'ASC')    // Sắp xếp các trang theo thứ tự 'order'
	                        ->get();                     // Lấy tất cả các kết quả thỏa mãn điều kiện

	    // Đường dẫn đến trang chủ
	    $home = route('home'); 

	    // Khởi tạo biến builder để lưu HTML cho dropdowns và non-dropdowns
	    $dropdownsBuilder = ''; 

	    // Tạo HTML cho menu không có dropdown (bao gồm trang chủ)
	    $nonDropdownsBuilder = "<li class='nav-item'>
	        <a class='nav-link' href='" . $home . "'>" . __('Home') . "</a>
	    </li>";
	    
	    // Khởi tạo biến HTML cuối cùng
	    $html = '';

	    // Duyệt qua các trang không có dropdown để tạo menu item
	    foreach ($nonDropdowns as $page) {
	        $route = route('pages', $page->slug);  // Lấy route của trang hiện tại
	        $nonDropdownsBuilder .= "
	            <li class='nav-item'>
	                <a class='nav-link' href='" . $route . "'>" . __($page->name) . "</a>
	            </li>
	        ";
	    }

	    // Nếu có ít nhất một trang không có dropdown, thêm chúng vào HTML
	    if ($nonDropdowns->count() > 0) {
	        $html .= $nonDropdownsBuilder;
	    }

	    // Duyệt qua các trang có dropdown để tạo menu item trong dropdown
	    foreach ($dropdowns as $drop) {
	        $route = route('pages', $drop->slug);  // Lấy route của trang hiện tại
	        $dropdownsBuilder .= "<li><a class='dropdown-item' href='" . $route . "'>" . __($drop->name) . "</a></li>";
	    }

	    // Nếu có ít nhất một trang có dropdown, tạo dropdown menu trong navbar
	    if ($dropdowns->count() > 0) {
	        $html .= " 
	            <li class='nav-item dropdown'>
	                <a class='nav-link dropdown-toggle' href='#' id='navbarDropdown' role='button'
	                   data-bs-toggle='dropdown' aria-expanded='false'>
	                    " . __('Pages') . "
	                </a>
	                <ul class='dropdown-menu' aria-labelledby='navbarDropdown'>
	                    " . $dropdownsBuilder . "
	                </ul>
	            </li>";
	    }

	    // Trả về HTML cuối cùng của navbar
	    return $html;
	}


    public static function trans($key)
    {
        $jsonFile = session('locale') ?? 'en';

        $jsonArray = json_decode(file_get_contents(resource_path('lang/sections/' . $jsonFile . '.json')), true) ?? [];


        $key = preg_replace('/\s+/S', " ", $key);

        $key = ucfirst(strtolower(trim($key)));

        if (!array_key_exists($key, $jsonArray)) {

            $jsonArray[$key] = $key;

            file_put_contents(resource_path('lang/sections/' . $jsonFile . '.json'), json_encode($jsonArray));
        }

        return $jsonArray[$key];
    /**
     * Phương thức trans thực hiện các bước sau:
     	* Lấy ngôn ngữ: Xác định ngôn ngữ hiện tại của người dùng từ session.
     	* Đọc tệp JSON: Lấy các bản dịch từ tệp JSON tương ứng với ngôn ngữ của người dùng.
     	* Chuẩn hóa key: Chuẩn hóa key (loại bỏ khoảng trắng dư thừa và đảm bảo định dạng đúng).
     	* Kiểm tra key: Kiểm tra xem key đã có trong tệp JSON chưa. Nếu chưa, thêm key vào tệp JSON với giá trị mặc định là chính key đó.
     	* Trả về giá trị dịch: Trả về giá trị dịch hoặc key nếu chưa có bản dịch.
    */
    }
}