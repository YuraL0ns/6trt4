<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Order;
use App\Models\User;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('group:admin');
    }

    /**
     * Главная страница админ-панели
     */
    public function index()
    {
        $stats = [
            'total_users' => User::count(),
            'total_photographers' => User::where('group', 'photo')->count(),
            'total_events' => Event::count(),
            'total_orders' => Order::count(),
            'total_revenue' => Order::where('status', 'paid')->sum('total_amount'),
        ];

        $recentOrders = Order::with('user')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // Получаем последние непрочитанные оповещения
        $recentNotifications = Notification::where('user_id', Auth::id())
            ->whereNull('read_at')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return view('admin.dashboard', compact('stats', 'recentOrders', 'recentNotifications'));
    }
}
