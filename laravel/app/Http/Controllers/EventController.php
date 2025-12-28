<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Photo;
use App\Models\Cart;
use App\Services\PhotoProcessingService;
use App\Http\Client\FastApiClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;

class EventController extends Controller
{
    protected PhotoProcessingService $processingService;
    protected FastApiClient $fastApiClient;

    public function __construct(PhotoProcessingService $processingService, FastApiClient $fastApiClient)
    {
        $this->processingService = $processingService;
        $this->fastApiClient = $fastApiClient;
    }

    /**
     * Список событий
     */
    public function index(Request $request)
    {
        // Показываем только опубликованные события (не архивированные)
        $query = Event::where('status', 'published')
            ->with('author');
        
        // Поиск по названию или городу
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('title', 'ilike', "%{$search}%")
                  ->orWhere('city', 'ilike', "%{$search}%");
            });
        }
        
        // Сортировка
        $sort = $request->get('sort', 'newest');
        if ($sort === 'oldest') {
            $query->orderBy('date', 'asc');
        } else {
            $query->orderBy('date', 'desc');
        }
        
        $events = $query->paginate(12)->withQueryString();

        return view('events.index', compact('events'));
    }

    /**
     * Показать событие
     */
    public function show(string $slug)
    {
        try {
            // Показываем только опубликованные события (не архивированные)
            $event = Event::where('status', 'published')
                ->where('slug', $slug)
                ->with(['author', 'photos'])
                ->firstOrFail();

        // Синхронизируем цены из event_info.json если они не установлены
        // Это нужно для событий, которые были опубликованы до добавления синхронизации
        $hasZeroPricePhotos = $event->photos()->where('price', 0)->exists();
        if ($hasZeroPricePhotos) {
            try {
                $this->processingService->syncPricesFromEventInfo($event);
                // Перезагружаем событие после синхронизации
                $event->refresh();
            } catch (\Exception $e) {
                \Log::warning("EventController::show: Failed to sync prices", [
                    'event_id' => $event->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        $photos = $event->photos()
            ->with('event') // Загружаем событие для каждой фотографии
            ->whereNotNull('custom_path')
            ->orderBy('created_at', 'asc')
            ->paginate(24);

        // Получаем все уникальные номера из фотографий события
        $allNumbers = $event->photos()
            ->whereNotNull('numbers')
            ->get()
            ->pluck('numbers')
            ->filter(function($numbers) {
                return !empty($numbers) && is_array($numbers);
            })
            ->flatten()
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->toArray();

        // Получаем временной диапазон из EXIF данных для фильтра
        $timeRange = $event->photos()
            ->whereNotNull('created_at_exif')
            ->selectRaw('MIN(created_at_exif) as min_time, MAX(created_at_exif) as max_time')
            ->first();
        
        $minTime = null;
        $maxTime = null;
        $hasTimeline = false;
        
        if ($timeRange && $timeRange->min_time && $timeRange->max_time) {
            try {
                $minTime = \Carbon\Carbon::parse($timeRange->min_time);
                $maxTime = \Carbon\Carbon::parse($timeRange->max_time);
                $hasTimeline = true;
            } catch (\Exception $e) {
                \Log::warning("EventController::show: Failed to parse time range", [
                    'event_id' => $event->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return view('events.show', compact('event', 'photos', 'allNumbers', 'minTime', 'maxTime', 'hasTimeline'));
        } catch (\Exception $e) {
            \Log::error("EventController::show error", [
                'slug' => $slug,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Получить данные фотографии для модального окна
     */
    public function getPhoto(string $slug, string $photoId)
    {
        $event = Event::where('status', 'published')
            ->where('slug', $slug)
            ->firstOrFail();

        $photo = Photo::where('event_id', $event->id)
            ->where('id', $photoId)
            ->firstOrFail();

        return response()->json([
            'id' => $photo->id,
            'url' => $photo->getDisplayUrl(),
            'fallback_url' => $photo->getFallbackUrl(),
            'price' => number_format($photo->getPriceWithCommission(), 0, ',', ' '),
            'has_faces' => $photo->has_faces ?? false,
            'numbers' => $photo->numbers ?? [],
            'in_cart' => $this->isPhotoInCart($photo->id),
        ]);
    }

    /**
     * Поиск похожих фотографий по селфи
     */
    public function findSimilar(Request $request, string $slug)
    {
        $event = Event::where('status', 'published')
            ->where('slug', $slug)
            ->firstOrFail();

        $request->validate([
            'photo' => 'required|image|mimes:jpeg,png,jpg|max:10240',
            'type' => 'required|in:face,number',
        ]);

        try {
            $imagePath = $request->file('photo')->store('temp', 'public');
            $fullPath = Storage::disk('public')->path($imagePath);

            if ($request->type === 'face') {
                $result = $this->processingService->searchSimilarFaces(
                    $fullPath,
                    $event->id,
                    0.6 // threshold (было 1.2 - слишком большой, не находил совпадения)
                );
            } else {
                $result = $this->processingService->searchByNumber(
                    $fullPath,
                    $event->id
                );
            }

            // Удаляем временный файл
            Storage::disk('public')->delete($imagePath);

            // Если задача запущена, возвращаем task_id
            if (isset($result['task_id'])) {
                return response()->json([
                    'task_id' => $result['task_id'],
                    'status' => 'processing'
                ]);
            }

            // Если результат готов
            return response()->json([
                'status' => 'completed',
                'results' => $result['results'] ?? [],
                'total' => $result['total_found'] ?? 0
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Получить статус задачи поиска
     */
    public function getSearchTaskStatus(string $taskId)
    {
        try {
            $status = $this->fastApiClient->getTaskStatus($taskId);
            
            // Преобразуем статус FastAPI в формат для фронтенда
            $response = [
                'task_id' => $taskId,
                'status' => 'processing',
                'state' => $status['state'] ?? 'PENDING'
            ];
            
            if ($status['state'] === 'SUCCESS') {
                $response['status'] = 'completed';
                $response['result'] = $status['result'] ?? null;
            } else if ($status['state'] === 'FAILURE' || $status['state'] === 'REVOKED') {
                $response['status'] = 'failed';
                $response['error'] = $status['error'] ?? $status['status'] ?? 'Задача завершилась с ошибкой';
            } else if ($status['state'] === 'PROGRESS') {
                $response['status'] = 'processing';
                $response['progress'] = $status['progress'] ?? 0;
            }
            
            return response()->json($response);
        } catch (\Exception $e) {
            return response()->json([
                'task_id' => $taskId,
                'status' => 'failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Проверить, находится ли фотография в корзине
     */
    protected function isPhotoInCart(string $photoId): bool
    {
        return Cart::where('photo_id', $photoId)
            ->where(function ($query) {
                if (Auth::check()) {
                    $query->where('user_id', Auth::id());
                } else {
                    $query->where('session_id', session()->getId());
                }
            })
            ->exists();
    }
}
