<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('group:admin');
    }

    /**
     * Список оповещений
     */
    public function index()
    {
        $notifications = Notification::where('user_id', Auth::id())
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('admin.notifications.index', compact('notifications'));
    }

    /**
     * Отметить оповещение как прочитанное
     */
    public function markAsRead(string $id)
    {
        $notification = Notification::where('user_id', Auth::id())
            ->findOrFail($id);
        
        $notification->markAsRead();

        return response()->json(['success' => true]);
    }

    /**
     * Отметить все как прочитанные
     */
    public function markAllAsRead()
    {
        Notification::where('user_id', Auth::id())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return back()->with('success', 'Все оповещения отмечены как прочитанные');
    }
}
