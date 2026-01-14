<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Photo;
use App\Models\Cart;
use App\Models\FaceSearch;
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
            // Администраторы и авторы могут просматривать все события, остальные - только опубликованные
            $query = Event::where('slug', $slug)
                ->with(['author', 'photos']);
            
            // Если пользователь не администратор и не автор события, показываем только опубликованные события
            if (Auth::check()) {
                $user = Auth::user();
                if (!$user->isAdmin()) {
                    // Разрешаем автору видеть свои черновики
                    $query->where(function($q) use ($user) {
                        $q->where('status', 'published')
                          ->orWhere(function($subQ) use ($user) {
                              $subQ->where('status', 'draft')
                                   ->where('author_id', $user->id);
                          });
                    });
                }
            } else {
                // Неавторизованные пользователи видят только опубликованные события
                $query->where('status', 'published');
            }
            
            $event = $query->firstOrFail();

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
        // Администраторы могут просматривать фото всех событий, остальные - только опубликованных
        $query = Event::where('slug', $slug);
        
        // Если пользователь не администратор, показываем только опубликованные события
        if (!Auth::check() || !Auth::user()->isAdmin()) {
            $query->where('status', 'published');
        }
        
        $event = $query->firstOrFail();

        $photo = Photo::where('event_id', $event->id)
            ->where('id', $photoId)
            ->firstOrFail();

        // Получаем список ID фотографий в корзине для фильтрации
        $cartPhotoIds = $this->getCartPhotoIds();

        return response()->json([
            'id' => $photo->id,
            'url' => $photo->getDisplayUrl(),
            'fallback_url' => $photo->getFallbackUrl(),
            'proxy_url' => route('events.photo.proxy', ['slug' => $slug, 'photoId' => $photoId]), // URL для прокси через Laravel
            'price' => number_format($photo->getPriceWithCommission(), 0, ',', ' '),
            'has_faces' => $photo->has_faces ?? false,
            'numbers' => $photo->numbers ?? [],
            'in_cart' => $this->isPhotoInCart($photo->id),
            'cart_photo_ids' => $cartPhotoIds, // Список ID фотографий в корзине
        ]);
    }

    /**
     * Прокси для получения изображения (решает проблему CORS)
     */
    public function getPhotoProxy(string $slug, string $photoId)
    {
        // Администраторы могут просматривать фото всех событий, остальные - только опубликованных
        $query = Event::where('slug', $slug);
        
        // Если пользователь не администратор, показываем только опубликованные события
        if (!Auth::check() || !Auth::user()->isAdmin()) {
            $query->where('status', 'published');
        }
        
        $event = $query->firstOrFail();

        $photo = Photo::where('event_id', $event->id)
            ->where('id', $photoId)
            ->firstOrFail();

        $url = $photo->getDisplayUrl();
        
        // Если URL внешний (S3), загружаем через Laravel
        if (strpos($url, 'http') === 0) {
            try {
                $imageContent = file_get_contents($url);
                $mimeType = getimagesizefromstring($imageContent)['mime'] ?? 'image/jpeg';
                
                return response($imageContent, 200)
                    ->header('Content-Type', $mimeType)
                    ->header('Cache-Control', 'public, max-age=3600');
            } catch (\Exception $e) {
                \Log::error("EventController::getPhotoProxy: Failed to fetch image", [
                    'photo_id' => $photoId,
                    'url' => $url,
                    'error' => $e->getMessage()
                ]);
                abort(404);
            }
        } else {
            // Локальный файл
            $filePath = storage_path('app/public/' . ltrim($url, '/'));
            if (file_exists($filePath)) {
                return response()->file($filePath);
            }
            abort(404);
        }
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
            'photo' => 'required|image|mimes:jpeg,png,jpg,webp|max:10240',
            'type' => 'required|in:face,number',
        ], [
            'photo.required' => 'Фотография обязательна для загрузки',
            'photo.image' => 'Файл должен быть изображением',
            'photo.mimes' => 'Файл должен быть в формате JPEG, PNG, JPG или WebP',
            'photo.max' => 'Размер файла не должен превышать 10 МБ',
            'type.required' => 'Тип поиска обязателен',
            'type.in' => 'Тип поиска должен быть face или number',
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

            // ИСПРАВЛЕНИЕ ОШИБКИ 3: Добавлено логирование ошибок поиска
            // Старый код не логировал ошибки, которые возвращались из FastAPI
            // Новый код: Проверяем статус результата и логируем ошибки
            if (isset($result['status']) && $result['status'] === 'error') {
                $errorMessage = $result['error'] ?? 'Unknown error';
                \Log::error("EventController::search: Search error from FastAPI", [
                    'event_id' => $event->id,
                    'search_type' => $request->type,
                    'error' => $errorMessage,
                    'full_result' => $result,
                    'image_path' => $imagePath,
                    'user_id' => Auth::id()
                ]);
                
                // Удаляем временный файл при ошибке
                if (Storage::disk('public')->exists($imagePath)) {
                    Storage::disk('public')->delete($imagePath);
                }
                
                return response()->json([
                    'status' => 'error',
                    'error' => $errorMessage,
                    'results' => [],
                    'total' => 0
                ], 500);
            }

            // Если задача запущена, сохраняем путь к фото в сессии для последующего сохранения результатов
            if (isset($result['task_id'])) {
                // Сохраняем путь к загруженному фото в сессии
                session()->put("search_photo_{$result['task_id']}", $imagePath);
                
                \Log::info("EventController::search: Search task started", [
                    'event_id' => $event->id,
                    'search_type' => $request->type,
                    'task_id' => $result['task_id']
                ]);
                
                return response()->json([
                    'task_id' => $result['task_id'],
                    'status' => 'processing'
                ]);
            }

            // Если результат готов сразу, сохраняем результаты
            if (isset($result['results']) && $request->type === 'face') {
                $this->saveFaceSearchResults($result['results'], $imagePath);
            }

            // Удаляем временный файл только если результат готов сразу
            if (!isset($result['task_id'])) {
                Storage::disk('public')->delete($imagePath);
            }

            // Логируем успешный результат
            \Log::info("EventController::search: Search completed successfully", [
                'event_id' => $event->id,
                'search_type' => $request->type,
                'results_count' => count($result['results'] ?? []),
                'total_found' => $result['total_found'] ?? 0
            ]);

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
                $result = $status['result'] ?? null;
                
                // КРИТИЧЕСКОЕ ИСПРАВЛЕНИЕ: Правильно извлекаем результаты из ответа задачи
                // Результат задачи имеет формат: {"status": "completed", "results": [...], "total_found": N}
                if ($result && is_array($result)) {
                    $response['results'] = $result['results'] ?? [];
                    $response['total'] = $result['total_found'] ?? count($response['results']);
                    $response['result'] = $result; // Оставляем для обратной совместимости
                    
                    // КРИТИЧЕСКОЕ ИСПРАВЛЕНИЕ: Сохраняем результаты поиска в FaceSearch
                    // Получаем путь к загруженному фото из сессии
                    $userPhotoPath = session()->get("search_photo_{$taskId}");
                    if ($userPhotoPath) {
                        $this->saveFaceSearchResults($response['results'], $userPhotoPath);
                        // Удаляем из сессии после использования
                        session()->forget("search_photo_{$taskId}");
                        // Удаляем временный файл
                        Storage::disk('public')->delete($userPhotoPath);
                    }
                } else {
                    $response['results'] = [];
                    $response['total'] = 0;
                    $response['result'] = $result;
                }
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

    /**
     * Получить список ID фотографий в корзине
     */
    protected function getCartPhotoIds(): array
    {
        $query = Cart::query();
        
        if (Auth::check()) {
            $query->where('user_id', Auth::id());
        } else {
            $query->where('session_id', session()->getId());
        }
        
        return $query->pluck('photo_id')->toArray();
    }

    /**
     * Сохранить результаты поиска лиц в базу данных
     */
    private function saveFaceSearchResults(array $results, ?string $userPhotoPath = null): void
    {
        try {
            foreach ($results as $result) {
                if (!isset($result['photo_id']) || !isset($result['similarity'])) {
                    continue;
                }

                // Преобразуем similarity (0-1) в проценты (0-100)
                $similarityScore = floatval($result['similarity']) * 100;

                // Нормализуем путь к фото пользователя
                $normalizedPath = null;
                if ($userPhotoPath) {
                    // Убираем префикс storage/app/public/ если есть
                    $normalizedPath = str_replace('storage/app/public/', '', $userPhotoPath);
                    $normalizedPath = str_replace('temp/', '', $normalizedPath);
                }

                FaceSearch::create([
                    'photo_id' => $result['photo_id'],
                    'user_uploaded_photo_path' => $normalizedPath,
                    'similarity_score' => $similarityScore,
                ]);
            }
        } catch (\Exception $e) {
            \Log::error("Error saving face search results", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            // Не прерываем выполнение, просто логируем ошибку
        }
    }
}
