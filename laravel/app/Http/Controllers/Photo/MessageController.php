<?php

namespace App\Http\Controllers\Photo;

use App\Http\Controllers\Controller;
use App\Models\PhotographerMessage;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class MessageController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        // Администратор также имеет доступ к сообщениям
        $this->middleware(function ($request, $next) {
            if (!auth()->user()->isAdmin() && !auth()->user()->isPhotographer()) {
                abort(403, 'Доступ запрещен');
            }
            return $next($request);
        });
    }

    /**
     * Список чатов с пользователями
     */
    public function index()
    {
        // Администратор видит все чаты, фотограф - только свои
        $query = PhotographerMessage::query();
        if (Auth::user()->isPhotographer()) {
            $query->where('photographer_id', Auth::id());
        }
        
        $conversations = $query->with('user')
            ->select('user_id', DB::raw('MAX(created_at) as last_message_at'))
            ->groupBy('user_id')
            ->orderBy('last_message_at', 'desc')
            ->get();

        return view('photo.messages.index', compact('conversations'));
    }

    /**
     * Показать чат с пользователем
     */
    public function show(string $userId)
    {
        $user = User::findOrFail($userId);

        // Администратор видит все сообщения, фотограф - только свои
        $query = PhotographerMessage::query();
        if (Auth::user()->isPhotographer()) {
            $query->where('photographer_id', Auth::id());
        }
        
        $messages = $query->where('user_id', $userId)
            ->orderBy('created_at', 'asc')
            ->get();

        // Отмечаем сообщения как прочитанные
        if (Auth::user()->isPhotographer()) {
            PhotographerMessage::where('photographer_id', Auth::id())
                ->where('user_id', $userId)
                ->whereNull('read_at')
                ->update(['read_at' => now()]);
        }

        return view('photo.messages.show', compact('user', 'messages'));
    }

    /**
     * Отправить сообщение
     */
    public function store(Request $request, string $userId)
    {
        $request->validate([
            'message' => 'required|string|max:5000',
        ]);

        // Администратор может отправлять сообщения от имени любого фотографа
        // Для простоты используем ID администратора как photographer_id
        PhotographerMessage::create([
            'photographer_id' => Auth::id(),
            'user_id' => $userId,
            'message' => $request->message,
        ]);

        return back()->with('success', 'Сообщение отправлено');
    }
}
