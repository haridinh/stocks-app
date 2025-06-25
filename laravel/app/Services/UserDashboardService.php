<?php

namespace App\Services;

use App\Helpers\Helper\Helper;
use App\Models\DashboardSignal;
use App\Models\Deposit;
use App\Models\Payment;
use App\Models\UserSignal;
use App\Models\Withdraw;
use Carbon\Carbon;
use DB;

class UserDashboardService
{
    public function dashboard()
    {
        $user = auth()->user();

        $data['currentPlan'] = $user->currentplan()->first();

        if ($data['currentPlan'] != null) {
            $data['signalGraph'] =  UserSignal::where('user_id', auth()->id())->select(DB::raw('COUNT(*) as total'), DB::raw('MONTHNAME(created_at) month'))
            ->groupby('month')
            ->get();
        }


        $data['totalbalance'] = $user->balance;
        $data['totalDeposit'] = $user->deposits()->where('status', 1)->sum('amount');
        $data['totalWithdraw'] = $user->withdraws()->where('status', 1)->sum('withdraw_amount');
        $data['totalPayments'] = $user->payments()->where('status', 1)->sum('amount');
        $data['totalSupportTickets'] = $user->tickets()->count();
        $data['user'] = $user;
        $data['transactions'] = $user->transactions()->latest()->with('user')->limit(3)->get();

        $data['signals'] = DashboardSignal::where('user_id', $user->id)->latest()->with('signal.market', 'signal.pair', 'signal.time', 'user')->paginate(Helper::pagination());


        $months = array();

        $totalAmount = collect([]);

        $withdrawTotalAmount = collect([]);
        $depositTotalAmount = collect([]);
        $signalGrapTotal = collect([]);

        for ($i = 11; $i >= 0; $i--) {
            $month = Carbon::today()->startOfMonth()->subMonth($i);
            array_push($months, $month->monthName);

            $totalAmount->push(0);
            $withdrawTotalAmount->push(0);
            $depositTotalAmount->push(0);
            $signalGrapTotal->push(0);
        }

        $payment = Payment::where('status', 1)
            ->where('user_id', auth()->id())
            ->whereYear('created_at', '=', now())
            ->select(DB::raw('SUM(amount) as total'), DB::raw('MONTHNAME(created_at) month'))
            ->groupby('month')->get();

        $withdraw = Withdraw::where('status', 1)
            ->where('user_id', auth()->id())
            ->select(DB::raw('SUM(withdraw_amount) as total'), DB::raw('MONTHNAME(created_at) month'))
            ->groupby('month')
            ->get();


        $deposit = Deposit::where('status', 1)
            ->where('user_id', auth()->id())
            ->select(DB::raw('SUM(amount) as total'), DB::raw('MONTHNAME(created_at) month'))
            ->groupby('month')
            ->get();

        foreach ($payment as $pay) {
            $result = array_search($pay->month, $months);

            $totalAmount[$result] = $pay->total;
        }

        foreach ($withdraw as $with) {
            $result = array_search($with->month, $months);

            $withdrawTotalAmount[$result] = $with->total;
        }

        foreach ($deposit as $depo) {

            $result = array_search($depo->month, $months);
            $depositTotalAmount[$result] = $depo->total;
        }


        $graphs = $data['signalGraph'] ?? [];


        foreach ($graphs as $sig) {
            $result = array_search($sig->month, $months);

            $signalGrapTotal[$result] = $sig->total;
        }

        $data['totalAmount'] = $totalAmount;
        $data['withdrawTotalAmount'] = $withdrawTotalAmount;
        $data['depositTotalAmount'] = $depositTotalAmount;
        $data['signalGrapTotal'] = $signalGrapTotal;
        $data['months'] = $months;


        return $data;
    }
}
