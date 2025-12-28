<?php

namespace App\Http\Controllers\Photo;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        // Администратор также имеет доступ к аналитике
        $this->middleware(function ($request, $next) {
            if (!auth()->user()->isAdmin() && !auth()->user()->isPhotographer()) {
                abort(403, 'Доступ запрещен');
            }
            return $next($request);
        });
    }

    /**
     * Аналитика продаж фотографа
     */
    public function index()
    {
        // Администратор видит аналитику всех событий, фотограф - только свои
        $query = Event::query();
        if (Auth::user()->isPhotographer()) {
            $query->where('author_id', Auth::id());
        }
        
        $events = $query->with(['photos.orderItems.order'])
            ->get();

        $analytics = [];
        $totalEarnings = 0;
        $totalPhotosSold = 0;

        foreach ($events as $event) {
            $photosSold = 0;
            $earnings = 0;

            foreach ($event->photos as $photo) {
                $soldCount = $photo->orderItems->count();
                $photosSold += $soldCount;
                $earnings += $photo->orderItems->sum('price');
            }

            $analytics[] = [
                'event' => $event,
                'photos_sold' => $photosSold,
                'earnings' => $earnings,
            ];

            $totalPhotosSold += $photosSold;
            $totalEarnings += $earnings;
        }

        return view('photo.analytics', compact('analytics', 'totalEarnings', 'totalPhotosSold'));
    }
}
