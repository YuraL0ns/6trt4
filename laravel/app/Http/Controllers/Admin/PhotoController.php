<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Photo;
use App\Models\Event;
use Illuminate\Http\Request;

class PhotoController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('group:admin');
    }

    /**
     * Показать фото с выделенными областями лиц
     */
    public function showWithFaces(string $photoId)
    {
        $photo = Photo::with('event')->findOrFail($photoId);
        
        return view('admin.photos.show-with-faces', compact('photo'));
    }

    /**
     * Список всех фото с лицами
     */
    public function index(Request $request)
    {
        $query = Photo::with('event')
            ->where('has_faces', true);
        
        // Фильтр по событию
        if ($request->has('event_id') && $request->event_id) {
            $query->where('event_id', $request->event_id);
        }
        
        $photos = $query->orderBy('created_at', 'desc')
            ->paginate(20);
        
        $events = Event::orderBy('title')->get();
        
        return view('admin.photos.index', compact('photos', 'events'));
    }

    /**
     * Просмотр всех фотографий с данными
     */
    public function list(Request $request)
    {
        $query = Photo::with('event');

        // Поиск по ID фотографии
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                  ->orWhere('original_name', 'like', "%{$search}%")
                  ->orWhere('custom_name', 'like', "%{$search}%");
            });
        }

        // Фильтр по событию
        if ($request->has('event_id') && $request->event_id) {
            $query->where('event_id', $request->event_id);
        }

        // Фильтр по наличию лиц
        if ($request->has('has_faces')) {
            $hasFaces = $request->has_faces === '1';
            $query->where('has_faces', $hasFaces);
        }

        // Фильтр по наличию номеров
        if ($request->has('has_numbers')) {
            $hasNumbers = $request->has_numbers === '1';
            if ($hasNumbers) {
                // Для PostgreSQL нужно использовать правильный синтаксис для проверки JSON массива
                // Проверяем, что поле не null и JSON массив не пустой
                $query->whereNotNull('numbers')
                      ->whereRaw("jsonb_array_length(numbers::jsonb) > 0");
            } else {
                $query->where(function($q) {
                    $q->whereNull('numbers')
                      ->orWhereRaw("jsonb_array_length(numbers::jsonb) = 0");
                });
            }
        }

        // Фильтр по статусу
        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        // Сортировка
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $photos = $query->paginate(50)->withQueryString();
        $events = Event::orderBy('title')->get();

        // Статистика
        $stats = [
            'total' => Photo::count(),
            'with_faces' => Photo::where('has_faces', true)->count(),
            'with_numbers' => Photo::whereNotNull('numbers')
                ->whereRaw("jsonb_array_length(numbers::jsonb) > 0")
                ->count(),
            'with_s3' => Photo::whereNotNull('s3_custom_url')->count(),
        ];

        return view('admin.photos.list', compact('photos', 'events', 'stats'));
    }

    /**
     * Детальный просмотр фотографии
     */
    public function show(string $id)
    {
        $photo = Photo::with(['event', 'carts', 'orderItems', 'faceSearches'])->findOrFail($id);
        
        return view('admin.photos.show', compact('photo'));
    }
}

