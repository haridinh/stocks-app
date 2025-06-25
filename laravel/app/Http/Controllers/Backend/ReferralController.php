<?php

namespace App\Http\Controllers\Backend; // Định nghĩa namespace cho controller, giúp phân loại mã nguồn

// Import các lớp cần thiết từ Laravel và mô hình
use App\Http\Controllers\Controller; // Lớp Controller cơ bản của Laravel
use Illuminate\Http\Request; // Lớp Request của Laravel để nhận dữ liệu từ client
use App\Models\Referral; // Lớp Referral dùng để làm việc với cơ sở dữ liệu của bảng 'referrals'
use App\Models\RefferedCommission; // Lớp RefferedCommission (chưa sử dụng trong mã này)
use App\Models\User; // Lớp User (chưa sử dụng trong mã này)
use Carbon\Carbon; // Lớp Carbon giúp xử lý thời gian và ngày tháng

class ReferralController extends Controller
{
    // Hàm hiển thị trang quản lý referral
    public function index()
    {
        // Đặt tiêu đề trang
        $data['title'] = 'Manage Referral';

        // Lấy thông tin referral đầu tiên với type là 'invest'
        $data['invest_referral'] = Referral::where('type','invest')->latest()->first();

        // Lấy thông tin referral đầu tiên với type là 'interest'
        $data['interest_referral'] = Referral::where('type','interest')->latest()->first();

        // Trả về view backend.referral.index kèm theo dữ liệu referral
        return view('backend.referral.index')->with($data);
    }

    // Hàm xử lý việc lưu hoặc cập nhật thông tin referral của type 'invest' hoặc 'interest'
    public function investStore(Request $request)
    {
        // Tìm referral theo type (invest hoặc interest)
        $refferal = Referral::where('type', $request->type)->first();

        // Nếu không tìm thấy referral nào, tạo mới một đối tượng Referral
        if(!$refferal){
            $refferal = new Referral();
        }

        // Gán các giá trị từ request vào đối tượng referral
        $refferal->level = $request->level; // Cấp độ referral
        $refferal->commission = $request->commision; // Hoa hồng referral

        $refferal->type = $request->type; // Loại referral ('invest' hoặc 'interest')

        // Lưu thông tin referral vào cơ sở dữ liệu
        $refferal->save();

        // Quay lại trang trước đó với thông báo thành công
        return redirect()->back()->with('success', 'Refferal Level Updated Successfully');
    }

    // Hàm thay đổi trạng thái của referral (kích hoạt hay vô hiệu hóa)
    public function refferalStatusChange(Request $request)
    {
        // Tìm referral theo ID được truyền từ request
        $refferal = Referral::findOrFail($request->id);

        // Nếu trạng thái referral đang là true, đổi thành false (vô hiệu hóa)
        if ($request->status) {
            $refferal->status = false;
        } else {
            // Nếu trạng thái referral đang là false, đổi thành true (kích hoạt)
            $refferal->status = true;
        }

        // Lưu thay đổi vào cơ sở dữ liệu
        $refferal->save();

        // Tạo thông báo thay đổi trạng thái thành công
        $notify = ['success' => 'Plan Status Change Successfully'];

        // Trả về phản hồi JSON với thông báo thành công
        return response($notify);
    }
}
