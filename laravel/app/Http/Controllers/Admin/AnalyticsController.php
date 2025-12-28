<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Event;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('group:admin');
    }

    /**
     * Аналитика продаж
     */
    public function index(Request $request)
    {
        $startDate = $request->get('start_date', now()->startOfMonth());
        $endDate = $request->get('end_date', now()->endOfMonth());

        $orders = Order::where('status', 'paid')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();

        $totalRevenue = $orders->sum('total_amount');
        $totalOrders = $orders->count();
        $totalPhotos = $orders->sum(function ($order) {
            return $order->items->count();
        });

        // Комиссия платформы (20% по умолчанию, можно получить из настроек)
        $commissionPercent = \App\Models\Setting::get('percent_for_sales', 20);
        $platformCommission = $totalRevenue * ($commissionPercent / 100);
        $photographersEarnings = $totalRevenue - $platformCommission;

        // Статистика по месяцам
        $monthlyStats = Order::where('status', 'paid')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->select(
                DB::raw("DATE_TRUNC('month', created_at) as month"),
                DB::raw('COUNT(*) as orders_count'),
                DB::raw('SUM(total_amount) as revenue')
            )
            ->groupBy('month')
            ->orderBy('month', 'asc')
            ->get();

        return view('admin.analytics', compact(
            'totalRevenue',
            'totalOrders',
            'totalPhotos',
            'platformCommission',
            'photographersEarnings',
            'monthlyStats',
            'startDate',
            'endDate'
        ));
    }
}
