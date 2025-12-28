<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Event;
use Illuminate\Http\Request;

class PhotographerController extends Controller
{
    /**
     * Список всех фотографов
     */
    public function index()
    {
        // Показываем фотографов и администраторов (они тоже могут быть фотографами)
        $photographers = User::whereIn('group', ['photo', 'admin'])
            ->where('status', 'active')
            ->withCount('events')
            ->orderBy('created_at', 'desc')
            ->paginate(12);

        return view('photographers.index', compact('photographers'));
    }

    /**
     * Показать страницу фотографа
     */
    public function show(string $hashLogin)
    {
        // Показываем фотографов и администраторов
        $photographer = User::where('hash_login', $hashLogin)
            ->whereIn('group', ['photo', 'admin'])
            ->where('status', 'active')
            ->firstOrFail();

        $events = Event::where('author_id', $photographer->id)
            ->where('status', 'published')
            ->orderBy('date', 'desc')
            ->paginate(6);

        return view('photographers.show', compact('photographer', 'events'));
    }
}
