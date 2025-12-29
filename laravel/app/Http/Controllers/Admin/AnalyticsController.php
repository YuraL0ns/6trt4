<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
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
        $startDate = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->endOfMonth()->format('Y-m-d'));

        // Запрос для статистики (все оплаченные заказы за период)
        $statsQuery = Order::where('status', 'paid')
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);

        $totalRevenue = $statsQuery->sum('total_amount');
        $totalOrders = $statsQuery->count();
        
        // Подсчет фотографий
        $totalPhotos = OrderItem::whereHas('order', function($q) use ($startDate, $endDate) {
            $q->where('status', 'paid')
              ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
        })->count();

        // Комиссия платформы (20% по умолчанию, можно получить из настроек)
        $commissionPercent = \App\Models\Setting::get('percent_for_sales', 20);
        $platformCommission = $totalRevenue * ($commissionPercent / 100);
        $photographersEarnings = $totalRevenue - $platformCommission;

        // Статистика по месяцам
        $monthlyStats = Order::where('status', 'paid')
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->select(
                DB::raw("DATE_TRUNC('month', created_at) as month"),
                DB::raw('COUNT(*) as orders_count'),
                DB::raw('SUM(total_amount) as revenue')
            )
            ->groupBy('month')
            ->orderBy('month', 'asc')
            ->get();

        // КРИТИЧЕСКОЕ ИСПРАВЛЕНИЕ: Список продаж с поиском
        $ordersQuery = Order::where('status', 'paid')
            ->with(['user', 'items.photo.event'])
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);

        // Поиск по email
        if ($request->has('search_email') && $request->search_email) {
            $ordersQuery->where('email', 'like', '%' . $request->search_email . '%');
        }

        // Поиск по телефону
        if ($request->has('search_phone') && $request->search_phone) {
            $ordersQuery->where('phone', 'like', '%' . $request->search_phone . '%');
        }

        // Поиск по названию события (через items->photo->event)
        if ($request->has('search_event') && $request->search_event) {
            $ordersQuery->whereHas('items.photo.event', function($q) use ($request) {
                $q->where('title', 'like', '%' . $request->search_event . '%');
            });
        }

        // Поиск по дате (уже применяется через whereBetween выше, но можно добавить точную дату)
        if ($request->has('search_date') && $request->search_date) {
            $searchDate = $request->search_date;
            $ordersQuery->whereDate('created_at', $searchDate);
        }

        $orders = $ordersQuery->orderBy('created_at', 'desc')->paginate(25)->withQueryString();

        return view('admin.analytics', compact(
            'totalRevenue',
            'totalOrders',
            'totalPhotos',
            'platformCommission',
            'photographersEarnings',
            'monthlyStats',
            'startDate',
            'endDate',
            'orders'
        ));
    }
}
