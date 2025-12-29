<?php

namespace App\Http\Controllers\Photo;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\CeleryTask;
use App\Services\PhotoUploadService;
use App\Services\PhotoProcessingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Helpers\LogHelper;
use Illuminate\Support\Str;

class EventController extends Controller
{
    protected PhotoUploadService $uploadService;
    protected PhotoProcessingService $processingService;

    public function __construct(
        PhotoUploadService $uploadService,
        PhotoProcessingService $processingService
    ) {
        $this->middleware('auth');
        // Администратор также имеет доступ к созданию событий
        $this->middleware(function ($request, $next) {
            if (!auth()->user()->isAdmin() && !auth()->user()->isPhotographer()) {
                abort(403, 'Доступ запрещен');
            }
            return $next($request);
        });
        $this->uploadService = $uploadService;
        $this->processingService = $processingService;
    }

    /**
     * Список событий фотографа
     */
    public function index()
    {
        // Администратор видит все события, фотограф - только свои
        $query = Event::query();
        if (Auth::user()->isPhotographer()) {
            $query->where('author_id', Auth::id());
        }
        
        $events = $query->withCount('photos')
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return view('photo.events.index', compact('events'));
    }

    /**
     * Форма создания события
     */
    public function create()
    {
        return view('photo.events.create');
    }

    /**
     * Сохранить событие
     */
    public function store(Request $request)
    {
        \Log::info("EventController::store: Request received", [
            'user_id' => Auth::id(),
            'has_cover' => $request->hasFile('cover'),
            'cover_size' => $request->hasFile('cover') ? $request->file('cover')->getSize() : 0,
            'cover_mime' => $request->hasFile('cover') ? $request->file('cover')->getMimeType() : null,
            'all_request_keys' => array_keys($request->all()),
            'content_type' => $request->header('Content-Type'),
            'content_length' => $request->header('Content-Length')
        ]);
        
        $request->validate([
            'title' => 'required|string|max:255',
            'city' => 'required|string|max:255',
            'date' => 'required|date',
            'cover' => 'required|image|mimes:jpeg,png,jpg|max:10240',
            'description' => 'nullable|string|max:5000',
        ]);

        \Log::info("EventController::store: Validation passed, starting event creation", [
            'user_id' => Auth::id(),
            'title' => $request->title,
            'city' => $request->city,
            'date' => $request->date,
            'cover_file_name' => $request->hasFile('cover') ? $request->file('cover')->getClientOriginalName() : null,
            'cover_file_size' => $request->hasFile('cover') ? $request->file('cover')->getSize() : 0,
            'cover_file_mime' => $request->hasFile('cover') ? $request->file('cover')->getMimeType() : null
        ]);

        // Генерируем slug
        $slug = Event::generateSlug($request->title);

        // Создаем событие сначала (нужен UUID для пути обложки)
        $event = Event::create([
            'author_id' => Auth::id(),
            'title' => $request->title,
            'slug' => $slug,
            'city' => $request->city,
            'date' => $request->date,
            'cover_path' => null, // Будет установлено после обработки
            'price' => 0, // Будет установлено позже
            'status' => 'draft',
            'description' => $request->description,
        ]);

        \Log::info("EventController::store: Event created", [
            'event_id' => $event->id,
            'event_uuid' => $event->id
        ]);

        // Создаем структуру папок для события заранее используя Storage facade
        // (согласно документации Laravel: Storage::makeDirectory)
        $directories = [
            "events/{$event->id}/upload",
            "events/{$event->id}/original_photo",
            "events/{$event->id}/custom_photo",
            "events/{$event->id}/covers"
        ];
        
        foreach ($directories as $dir) {
            if (!Storage::disk('public')->exists($dir)) {
                try {
                    Storage::disk('public')->makeDirectory($dir);
                    \Log::info("EventController::store: Directory created via Storage", [
                        'event_id' => $event->id,
                        'directory' => $dir
                    ]);
                } catch (\Exception $e) {
                    \Log::error("EventController::store: Failed to create directory", [
                        'event_id' => $event->id,
                        'directory' => $dir,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }

        // Создаем путь для обложки: /storage/events/{event_uuid}/covers/
        $coverDirectory = "events/{$event->id}/covers";
        $coverFilename = uniqid() . '_' . time() . '.' . $request->file('cover')->getClientOriginalExtension();
        $coverPath = "{$coverDirectory}/{$coverFilename}";
        $fullCoverPath = storage_path('app/public/' . $coverPath);
        
        // Создаем директорию для обложки
        $fullCoverDirectory = storage_path('app/public/' . $coverDirectory);
        if (!file_exists($fullCoverDirectory)) {
            mkdir($fullCoverDirectory, 0755, true);
            \Log::info("EventController::store: Created cover directory", [
                'event_id' => $event->id,
                'directory' => $fullCoverDirectory
            ]);
        }

        // Сохраняем временную копию обложки для отправки в FastAPI
        $coverFile = $request->file('cover');
        $tempCoverPath = $coverFile->storeAs($coverDirectory, $coverFilename, 'public');
        $tempFullCoverPath = storage_path('app/public/' . $tempCoverPath);

        \Log::info("EventController::store: Processing cover via FastAPI", [
            'event_id' => $event->id,
            'cover_path' => $coverPath,
            'full_cover_path' => $fullCoverPath,
            'temp_path' => $tempFullCoverPath
        ]);

        try {
            // Отправляем обложку в FastAPI для обработки
            $this->processEventCoverViaFastAPI($event, $tempFullCoverPath, $fullCoverPath);
            
            // Обновляем путь обложки в событии
            $event->update(['cover_path' => $coverPath]);
            
            // Удаляем временный файл
            if (file_exists($tempFullCoverPath) && $tempFullCoverPath !== $fullCoverPath) {
                @unlink($tempFullCoverPath);
            }
            
            \Log::info("EventController::store: Cover processed successfully", [
                'event_id' => $event->id,
                'cover_path' => $coverPath,
                'file_exists' => file_exists($fullCoverPath),
                'file_size' => file_exists($fullCoverPath) ? filesize($fullCoverPath) : 0
            ]);
        } catch (\Exception $e) {
            \Log::error("EventController::store: Error processing cover", [
                'event_id' => $event->id,
                'cover_path' => $coverPath,
                'full_cover_path' => $fullCoverPath,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            // Если обработка не удалась, используем оригинальный файл
            if (file_exists($tempFullCoverPath)) {
                $event->update(['cover_path' => $tempCoverPath]);
                \Log::warning("EventController::store: Using original cover file", [
                    'event_id' => $event->id,
                    'cover_path' => $tempCoverPath
                ]);
            }
        }

        return redirect()->route('photo.events.show', $event->slug)
            ->with('success', 'Событие создано');
    }

    /**
     * Показать событие
     */
    public function show(Event $event)
    {
        // Администратор видит все события, фотограф - только свои
        if (Auth::user()->isPhotographer() && $event->author_id !== Auth::id()) {
            abort(403, 'Доступ запрещен');
        }
        
        $event->load(['celeryTasks', 'author']);

        // Пагинация фотографий (20 на страницу)
        $photos = $event->photos()->paginate(48);

        // Читаем event_info.json для получения списка анализов
        $analyses = [];
        $eventInfoPath = storage_path('app/public/events/' . $event->id . '/event_info.json');
        
        // Логируем путь к файлу для отладки
        \Log::info('[DOCKER] FILE event_info.json - PATH: ' . $eventInfoPath, [
            'event_id' => $event->id,
            'file_exists' => file_exists($eventInfoPath),
            'realpath' => file_exists($eventInfoPath) ? realpath($eventInfoPath) : null
        ]);
        
        if (file_exists($eventInfoPath)) {
            try {
                $eventInfo = json_decode(file_get_contents($eventInfoPath), true);
                if (isset($eventInfo['analyze'])) {
                    $analyses = $eventInfo['analyze'];
                    // Нормализуем значения - конвертируем строки "1"/"0" в boolean
                    foreach ($analyses as $key => $value) {
                        if ($value === "1" || $value === 1 || $value === true) {
                            $analyses[$key] = true;
                        } elseif ($value === "0" || $value === 0 || $value === false) {
                            $analyses[$key] = false;
                        }
                    }
                }
            } catch (\Exception $e) {
                \Log::warning("EventController::show: Failed to read event_info.json", [
                    'event_id' => $event->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return view('photo.events.show', compact('event', 'analyses', 'photos'));
    }

    /**
     * Загрузить фотографии для события
     */
    public function uploadPhotos(Request $request, Event $event)
    {
        // КРИТИЧЕСКОЕ ЛОГИРОВАНИЕ В САМОМ НАЧАЛЕ МЕТОДА
        \Log::emergency("EventController::uploadPhotos: METHOD CALLED", [
            'event_id' => $event->id,
            'event_slug' => $event->slug,
            'user_id' => Auth::id(),
            'has_files' => $request->hasFile('photos'),
            'content_type' => $request->header('Content-Type'),
            'request_method' => $request->method(),
            'request_all_keys' => array_keys($request->all()),
            'request_size' => $request->header('Content-Length'),
            'php_upload_max_filesize' => ini_get('upload_max_filesize'),
            'php_post_max_size' => ini_get('post_max_size'),
            'php_max_file_uploads' => ini_get('max_file_uploads')
        ]);
        
        // Также пишем в файл напрямую для гарантии
        try {
            file_put_contents(
                storage_path('logs/upload_debug.log'),
                date('Y-m-d H:i:s') . " - uploadPhotos CALLED\n" .
                "Event ID: {$event->id}\n" .
                "Has files: " . ($request->hasFile('photos') ? 'YES' : 'NO') . "\n" .
                "Files count: " . ($request->hasFile('photos') ? count($request->file('photos')) : 0) . "\n" .
                "Content-Type: " . $request->header('Content-Type') . "\n" .
                "Content-Length: " . $request->header('Content-Length') . "\n" .
                "Request keys: " . implode(', ', array_keys($request->all())) . "\n" .
                "---\n",
                FILE_APPEND
            );
        } catch (\Exception $e) {
            \Log::error("Failed to write to upload_debug.log: " . $e->getMessage());
        }
        
        \Log::info("EventController::uploadPhotos: Request received", [
            'event_id' => $event->id,
            'user_id' => Auth::id(),
            'has_files' => $request->hasFile('photos'),
            'files_count' => $request->hasFile('photos') ? count($request->file('photos')) : 0,
            'content_type' => $request->header('Content-Type'),
            'request_method' => $request->method(),
            'request_all_keys' => array_keys($request->all())
        ]);
        
        LogHelper::info("EventController::uploadPhotos: Request received", [
            'event_id' => $event->id,
            'user_id' => Auth::id(),
            'has_files' => $request->hasFile('photos'),
            'files_count' => $request->hasFile('photos') ? count($request->file('photos')) : 0,
            'request_all' => $request->all()
        ]);

        // Администратор может загружать фото в любое событие, фотограф - только в свои
        if (Auth::user()->isPhotographer() && $event->author_id !== Auth::id()) {
            LogHelper::warning("EventController::uploadPhotos: Access denied", [
                'event_id' => $event->id,
                'user_id' => Auth::id(),
                'event_author_id' => $event->author_id
            ]);
            abort(403, 'Доступ запрещен');
        }

        try {
            \Log::debug("EventController::uploadPhotos: Starting validation", [
                'event_id' => $event->id,
                'has_photos' => $request->has('photos'),
                'photos_is_array' => is_array($request->input('photos')),
                'photos_count' => is_array($request->input('photos')) ? count($request->input('photos')) : 0
            ]);
            
            $request->validate([
                'photos' => 'required|array|min:1|max:15000',
                'photos.*' => 'required|image|mimes:jpeg,png,jpg,webp|max:20480', // 20MB
            ]);

            \Log::info("EventController::uploadPhotos: Validation passed", [
                'event_id' => $event->id
            ]);
            
            LogHelper::debug("EventController::uploadPhotos: Validation passed", [
                'event_id' => $event->id
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error("EventController::uploadPhotos: Validation failed", [
                'event_id' => $event->id,
                'errors' => $e->errors(),
                'request_data' => $request->all()
            ]);
            
            LogHelper::error("EventController::uploadPhotos: Validation failed", [
                'event_id' => $event->id,
                'errors' => $e->errors()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Ошибка валидации: ' . implode(', ', array_flatten($e->errors())),
                'errors' => $e->errors()
            ], 422);
        }

        try {
            $files = $request->file('photos');
            
            \Log::debug("EventController::uploadPhotos: Files extracted", [
                'event_id' => $event->id,
                'files_count' => is_array($files) ? count($files) : 0,
                'files_type' => gettype($files),
                'is_array' => is_array($files),
                'is_empty' => empty($files),
                'request_has_file_photos' => $request->hasFile('photos'),
                'request_all_keys' => array_keys($request->all())
            ]);
            
            LogHelper::debug("EventController::uploadPhotos: Files extracted", [
                'event_id' => $event->id,
                'files_count' => is_array($files) ? count($files) : 0,
                'files_type' => gettype($files),
                'is_array' => is_array($files),
                'is_empty' => empty($files)
            ]);
            
            if (empty($files) || !is_array($files)) {
                \Log::error("EventController::uploadPhotos: No files in request", [
                    'event_id' => $event->id,
                    'request_files' => $request->hasFile('photos'),
                    'files_count' => $request->hasFile('photos') ? count($request->file('photos')) : 0,
                    'php_upload_max_filesize' => ini_get('upload_max_filesize'),
                    'php_post_max_size' => ini_get('post_max_size'),
                    'php_max_file_uploads' => ini_get('max_file_uploads'),
                    'content_length' => $request->header('Content-Length'),
                    'content_type' => $request->header('Content-Type')
                ]);
                
                LogHelper::error("EventController::uploadPhotos: No files in request", [
                    'event_id' => $event->id,
                    'request_files' => $request->hasFile('photos'),
                    'files_count' => $request->hasFile('photos') ? count($request->file('photos')) : 0,
                    'php_upload_max_filesize' => ini_get('upload_max_filesize'),
                    'php_post_max_size' => ini_get('post_max_size'),
                    'php_max_file_uploads' => ini_get('max_file_uploads')
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Файлы не были загружены. Проверьте настройки PHP: upload_max_filesize=' . ini_get('upload_max_filesize') . ', post_max_size=' . ini_get('post_max_size') . ', max_file_uploads=' . ini_get('max_file_uploads'),
                    'debug' => [
                        'has_file_photos' => $request->hasFile('photos'),
                        'files_count' => $request->hasFile('photos') ? count($request->file('photos')) : 0,
                        'content_type' => $request->header('Content-Type')
                    ]
                ], 400);
            }

            \Log::info("EventController::uploadPhotos: Starting upload service", [
                'event_id' => $event->id,
                'files_count' => count($files)
            ]);
            
            LogHelper::info("EventController::uploadPhotos: Starting upload service", [
                'event_id' => $event->id,
                'files_count' => count($files)
            ]);

            $result = $this->uploadService->uploadPhotos($event, $files);

            \Log::info("EventController::uploadPhotos: Upload service completed", [
                'event_id' => $event->id,
                'uploaded' => $result['uploaded'],
                'errors' => $result['errors'],
                'error_details_count' => count($result['error_details'] ?? [])
            ]);
            
            LogHelper::info("EventController::uploadPhotos: Upload service completed", [
                'event_id' => $event->id,
                'uploaded' => $result['uploaded'],
                'errors' => $result['errors']
            ]);

            return response()->json([
                'success' => true,
                'uploaded' => $result['uploaded'],
                'errors' => $result['errors'],
                'error_details' => $result['error_details'] ?? [],
                'message' => "Загружено {$result['uploaded']} фотографий" . ($result['errors'] > 0 ? ", ошибок: {$result['errors']}" : '')
            ]);
        } catch (\Exception $e) {
            \Log::error("EventController::uploadPhotos: Exception", [
                'event_id' => $event->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            LogHelper::error("EventController::uploadPhotos: Exception", [
                'event_id' => $event->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error_type' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 400);
        }
    }

    /**
     * Получить прогресс загрузки фотографий
     */
    public function uploadProgress(Event $event)
    {
        try {
            $photoCount = $event->photos()->count();
            
            return response()->json([
                'success' => true,
                'count' => $photoCount
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'count' => 0,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Получить статус анализа
     */
    public function analysisStatus(Event $event)
    {
        // Администратор видит все события, фотограф - только свои
        if (Auth::user()->isPhotographer() && $event->author_id !== Auth::id()) {
            abort(403, 'Доступ запрещен');
        }
        
        $event->load('celeryTasks');

        // Обновляем статусы задач (проверяем все задачи, даже если они помечены как completed,
        // так как статус события может еще не обновиться)
        foreach ($event->celeryTasks as $task) {
            if ($task->task_id) {
                try {
                    $this->processingService->updateTaskStatus($task->task_id);
                } catch (\Exception $e) {
                    // Игнорируем ошибки обновления, но логируем их
                    \Log::warning("EventController::analysisStatus: Failed to update task status", [
                        'task_id' => $task->task_id,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }

        // Перезагружаем событие и задачи для получения актуальных данных
        $event->refresh();
        $event->load('celeryTasks');

        // Получаем event_info.json через FastAPI endpoint (polling)
        $fastApiClient = app(\App\Http\Client\FastApiClient::class);
        
        \Log::info('[DOCKER] FILE event_info.json - Requesting via FastAPI', [
            'event_id' => $event->id,
            'fastapi_url' => config('services.fastapi.url')
        ]);
        
        $eventInfo = $fastApiClient->getEventInfo($event->id);
        $analysisProgress = [];
        
        // Логируем получение данных
        \Log::info('[DOCKER] FILE event_info.json - Response received', [
            'event_id' => $event->id,
            'has_data' => !empty($eventInfo),
            'event_info_keys' => $eventInfo ? array_keys($eventInfo) : [],
            'photo_count' => $eventInfo['photo_count'] ?? null,
            'has_analyze' => isset($eventInfo['analyze']),
            'analyze_keys' => isset($eventInfo['analyze']) ? array_keys($eventInfo['analyze']) : []
        ]);
        
        if ($eventInfo) {
            try {
                
                // Получаем общее количество фотографий
                $totalPhotos = $eventInfo['photo_count'] ?? $event->photos()->count();
                
                // Подсчитываем прогресс для каждого типа анализа
                $analysisTypes = [
                    'timeline' => 'analyze_timeline',
                    'remove_exif' => 'analyze_removeexif',
                    'watermark' => 'analyze_watermark',
                    'face_search' => 'analyze_facesearch',
                    'number_search' => 'analyze_numbersearch'
                ];
                
                foreach ($analysisTypes as $taskType => $jsonKey) {
                    if (isset($eventInfo[$jsonKey]) && is_array($eventInfo[$jsonKey])) {
                        // Приводим статусы к единому стилю - проверяем только количество записей со статусом "ready"
                        // Статусы могут быть: "ready", "processing", "error" - но мы считаем только "ready"
                        $completed = count(array_filter($eventInfo[$jsonKey], function($item) {
                            // Приводим к единому стилю - проверяем только "ready"
                            $status = isset($item['status']) ? strtolower(trim($item['status'])) : '';
                            return $status === 'ready';
                        }));
                        // Используем общее количество фотографий из event_info.json или из БД
                        $taskTotalPhotos = $totalPhotos;
                        if (isset($eventInfo['photo_count']) && $eventInfo['photo_count'] > 0) {
                            $taskTotalPhotos = $eventInfo['photo_count'];
                        }
                        
                        $progress = $taskTotalPhotos > 0 ? intval(($completed / $taskTotalPhotos) * 100) : 0;
                        
                        // Определяем статус: completed только если все фотографии обработаны
                        $status = 'pending';
                        if ($taskTotalPhotos > 0) {
                            if ($completed === $taskTotalPhotos) {
                                $status = 'completed';
                            } elseif ($completed > 0) {
                                $status = 'processing';
                            }
                        }
                        
                        $analysisProgress[$taskType] = [
                            'completed' => $completed,
                            'total' => $taskTotalPhotos,
                            'progress' => $progress,
                            'status' => $status
                        ];
                        
                        \Log::debug("EventController::analysisStatus: Task progress calculated", [
                            'task_type' => $taskType,
                            'completed' => $completed,
                            'total' => $taskTotalPhotos,
                            'progress' => $progress,
                            'status' => $status,
                            'json_key' => $jsonKey,
                            'items_count' => count($eventInfo[$jsonKey])
                        ]);
                    } else {
                        // Если секция анализа не найдена в event_info.json, используем данные из задачи
                        $task = $event->celeryTasks->where('task_type', $taskType)->first();
                        if ($task) {
                            $analysisProgress[$taskType] = [
                                'completed' => 0,
                                'total' => $totalPhotos,
                                'progress' => $task->progress,
                                'status' => $task->status
                        ];
                        }
                    }
                }
                
                // Обновляем прогресс задач на основе event_info.json
                \Log::info("EventController::analysisStatus: Updating tasks from event_info.json", [
                    'event_id' => $event->id,
                    'analysis_progress' => $analysisProgress,
                    'tasks_count' => $event->celeryTasks->count(),
                    'tasks_types' => $event->celeryTasks->pluck('task_type')->toArray()
                ]);
                
                foreach ($event->celeryTasks as $task) {
                    \Log::info("EventController::analysisStatus: Processing task", [
                        'task_id' => $task->id,
                        'task_type' => $task->task_type,
                        'current_status' => $task->status,
                        'current_progress' => $task->progress,
                        'has_progress_data' => isset($analysisProgress[$task->task_type])
                    ]);
                    
                    if (isset($analysisProgress[$task->task_type])) {
                        $progressData = $analysisProgress[$task->task_type];
                        
                        \Log::info("EventController::analysisStatus: Task progress data", [
                            'task_id' => $task->id,
                            'task_type' => $task->task_type,
                            'progress_data' => $progressData,
                            'current_progress' => $task->progress,
                            'new_progress' => $progressData['progress'],
                            'current_status' => $task->status,
                            'new_status' => $progressData['status']
                        ]);
                        
                        // Обновляем прогресс если он изменился
                        if ($task->progress != $progressData['progress']) {
                            $task->update(['progress' => $progressData['progress']]);
                            \Log::info("EventController::analysisStatus: Task progress updated", [
                                'task_id' => $task->id,
                                'task_type' => $task->task_type,
                                'old_progress' => $task->getOriginal('progress'),
                                'new_progress' => $progressData['progress']
                            ]);
                        }
                        
                        // Обновляем статус на основе прогресса
                        $newStatus = $progressData['status'];
                        if ($newStatus === 'completed' && $task->status !== 'completed') {
                            $task->update(['status' => 'completed']);
                            \Log::info("EventController::analysisStatus: Task status updated to completed", [
                                'task_id' => $task->id,
                                'task_type' => $task->task_type
                            ]);
                        } elseif ($newStatus === 'processing' && $task->status === 'pending') {
                            $task->update(['status' => 'processing']);
                            \Log::info("EventController::analysisStatus: Task status updated to processing", [
                                'task_id' => $task->id,
                                'task_type' => $task->task_type
                            ]);
                        }
                    } else {
                        \Log::warning("EventController::analysisStatus: No progress data for task", [
                            'task_id' => $task->id,
                            'task_type' => $task->task_type,
                            'available_progress_keys' => array_keys($analysisProgress)
                        ]);
                    }
                }
                
                // Перезагружаем задачи после обновления
                $event->refresh();
                $event->load('celeryTasks');
                
                // Проверяем, все ли задачи завершены, и обновляем статус события
                // ВАЖНО: Не публикуем событие, если:
                // 1. Нет задач вообще
                // 2. Все задачи в статусе 'pending' (еще не запущены)
                // 3. Задачи не имеют task_id (не были отправлены в Celery)
                // 4. Не все задачи завершены
                $tasks = $event->celeryTasks;
                $hasTasks = $tasks->count() > 0;
                $allTasksCompleted = false;
                
                if ($hasTasks) {
                    // Проверяем, что все задачи либо завершены, либо провалились
                    // И что хотя бы одна задача была запущена (не все в pending)
                    $allInPending = $tasks->every(function ($task) {
                        return $task->status === 'pending';
                    });
                    
                    // Проверяем, что задачи имеют task_id (были отправлены в Celery)
                    $allHaveTaskId = $tasks->every(function ($task) {
                        return !empty($task->task_id);
                    });
                    
                    if (!$allInPending && $allHaveTaskId) {
                        // Не все в pending и все имеют task_id - проверяем завершение
                        // КРИТИЧНО: Задача считается завершенной только если:
                        // 1. Статус 'completed' или 'failed'
                        // 2. И прогресс >= 95% (для completed) или прогресс > 0 (для failed)
                        // 3. И задача не является timeline (timeline временно отключен)
                        $allTasksCompleted = $tasks->filter(function ($task) {
                            // Пропускаем timeline задачи
                            return $task->task_type !== 'timeline';
                        })->every(function ($task) {
                            if (in_array($task->status, ['completed', 'failed'])) {
                                // Для завершенных задач проверяем прогресс
                                if ($task->status === 'completed') {
                                    return $task->progress >= 95;
                                } else {
                                    // Для failed задач прогресс может быть любым
                                    return true;
                                }
                            }
                            return false;
                });
                    } else {
                        if ($allInPending) {
                            \Log::debug("EventController::analysisStatus: All tasks are pending, not publishing", [
                                'event_id' => $event->id,
                                'tasks_count' => $tasks->count()
                            ]);
                        }
                        if (!$allHaveTaskId) {
                            \Log::warning("EventController::analysisStatus: Some tasks don't have task_id (not sent to Celery), not publishing", [
                                'event_id' => $event->id,
                                'tasks_count' => $tasks->count(),
                                'tasks_without_id' => $tasks->filter(function($t) { return empty($t->task_id); })->pluck('task_type')->toArray()
                            ]);
                        }
                    }
                } else {
                    // Нет задач - не публикуем
                    \Log::debug("EventController::analysisStatus: No tasks found, not publishing", [
                        'event_id' => $event->id
                    ]);
                }
                
                // Дополнительная проверка: убеждаемся, что в event_info.json действительно есть данные об анализе
                // и что хотя бы одна задача имеет прогресс > 0 или статус 'processing'/'completed'
                if ($allTasksCompleted && $hasTasks) {
                    $hasRealProgress = $tasks->some(function ($task) use ($analysisProgress) {
                        $taskType = $task->task_type;
                        $progressData = $analysisProgress[$taskType] ?? null;
                        
                        // Проверяем, что задача действительно выполнялась
                        // Либо имеет прогресс > 0, либо статус processing/completed
                        if ($progressData) {
                            return ($progressData['progress'] > 0 || in_array($progressData['status'], ['processing', 'completed']));
                        }
                        // Если нет данных в event_info.json, проверяем статус задачи
                        return in_array($task->status, ['processing', 'completed', 'failed']);
                    });
                    
                    if (!$hasRealProgress) {
                        \Log::warning("EventController::analysisStatus: Tasks marked as completed but no real progress found", [
                            'event_id' => $event->id,
                            'tasks_count' => $tasks->count(),
                            'tasks_statuses' => $tasks->pluck('status')->toArray()
                        ]);
                        $allTasksCompleted = false;
                    }
                }
                
                if ($allTasksCompleted && in_array($event->status, ['processing', 'draft'])) {
                    \Log::info("EventController::analysisStatus: All tasks completed, updating event status to published", [
                        'event_id' => $event->id,
                        'tasks_count' => $tasks->count(),
                        'tasks_statuses' => $tasks->pluck('status')->toArray(),
                        'analysis_progress' => $analysisProgress
                    ]);
                    
                    $event->update(['status' => 'published']);
                    $event->refresh();
                    
                    \Log::info("EventController::analysisStatus: Event status updated to published", [
                        'event_id' => $event->id,
                        'tasks_count' => $tasks->count()
                    ]);
                } else {
                    \Log::debug("EventController::analysisStatus: Event not published", [
                        'event_id' => $event->id,
                        'all_tasks_completed' => $allTasksCompleted,
                        'has_tasks' => $hasTasks,
                        'event_status' => $event->status,
                        'tasks_count' => $tasks->count(),
                        'tasks_statuses' => $tasks->pluck('status')->toArray()
                    ]);
                }
                
            } catch (\Exception $e) {
                \Log::warning("EventController::analysisStatus: Failed to read event_info.json", [
                    'event_id' => $event->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Получаем общее количество фотографий для всех задач
        $totalPhotos = $event->photos()->count();

        return response()->json([
            'event_status' => $event->status,
            'total_photos' => $totalPhotos,
            'tasks' => $event->celeryTasks->map(function ($task) use ($analysisProgress, $totalPhotos) {
                $taskType = $task->task_type;
                $progressData = $analysisProgress[$taskType] ?? null;
                
                // Используем данные из event_info.json если они есть, иначе из задачи
                $completed = $progressData['completed'] ?? 0;
                $total = $progressData['total'] ?? $totalPhotos;
                $progress = $progressData['progress'] ?? $task->progress;
                $status = $progressData['status'] ?? $task->status;
                
                return [
                    'type' => $taskType,
                    'status' => $status,
                    'progress' => $progress,
                    'completed' => $completed,
                    'total' => $total,
                ];
            })
        ]);
    }

    /**
     * Запустить анализ фотографий
     */
    public function startAnalysis(Request $request, Event $event)
    {
        LogHelper::info("EventController::startAnalysis: Request received", [
            'event_id' => $event->id,
            'user_id' => Auth::id(),
            'request_data' => $request->all()
        ]);

        // Администратор может запускать анализ для любого события, фотограф - только для своих
        if (Auth::user()->isPhotographer() && $event->author_id !== Auth::id()) {
            LogHelper::warning("EventController::startAnalysis: Access denied", [
                'event_id' => $event->id,
                'user_id' => Auth::id(),
                'event_author_id' => $event->author_id
            ]);
            abort(403, 'Доступ запрещен');
        }

        try {
            $validated = $request->validate([
                'price' => 'required|numeric|min:0',
                'analyses' => 'required|array',
                // 'analyses.timeline' => 'nullable|boolean', // Timeline временно отключен
                'analyses.remove_exif' => 'nullable|boolean',
                'analyses.watermark' => 'nullable|boolean',
                'analyses.face_search' => 'nullable|boolean',
                'analyses.number_search' => 'nullable|boolean',
            ]);
            
            // Убеждаемся, что remove_exif и watermark всегда true
            $analyses = $validated['analyses'];
            $analyses['remove_exif'] = true;
            $analyses['watermark'] = true;

            LogHelper::debug("EventController::startAnalysis: Validation passed", [
                'event_id' => $event->id,
                'price' => $validated['price'],
                'analyses' => $analyses
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            LogHelper::error("EventController::startAnalysis: Validation failed", [
                'event_id' => $event->id,
                'errors' => $e->errors()
            ]);
            return back()->with('error', 'Ошибка валидации: ' . implode(', ', array_flatten($e->errors())));
        }

        try {
            // Обновляем цену события
            $event->update([
                'price' => $validated['price'],
            ]);

            LogHelper::info("EventController::startAnalysis: Event price updated", [
                'event_id' => $event->id,
                'price' => $validated['price']
            ]);

            // Создаем event_info.json
            LogHelper::info("EventController::startAnalysis: Creating event_info.json", [
                'event_id' => $event->id,
                'analyses' => $analyses
            ]);
            $this->createEventInfoJson($event, $analyses);

            // Запускаем обработку через FastAPI
            LogHelper::info("EventController::startAnalysis: Starting processing service", [
                'event_id' => $event->id,
                'analyses' => $analyses,
                'price' => $validated['price']
            ]);
            
            $this->processingService->processEventPhotos(
                $event,
                $analyses,
                $validated['price']
            );
            
            LogHelper::info("EventController::startAnalysis: Processing service started", [
                'event_id' => $event->id
            ]);
            
            return back()->with('success', 'Анализ запущен');
        } catch (\Exception $e) {
            LogHelper::error("EventController::startAnalysis: Exception", [
                'event_id' => $event->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            LogHelper::error("EventController::startAnalysis: Failed to start analysis", [
                'event_id' => $event->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return back()->with('error', 'Ошибка при запуске анализа: ' . $e->getMessage() . '. Проверьте логи и убедитесь, что TYPE_PROJECT=developers в .env для детального логирования.');
        }
    }

    /**
     * Создать event_info.json
     */
    private function createEventInfoJson(Event $event, array $analyses)
    {
        LogHelper::info("EventController::createEventInfoJson: Starting", [
            'event_id' => $event->id,
            'analyses' => $analyses
        ]);

        $photos = $event->photos()->where('status', '!=', 'deleted')->get();
        LogHelper::debug("EventController::createEventInfoJson: Photos loaded", [
            'event_id' => $event->id,
            'photos_count' => $photos->count()
        ]);

        // Получаем комиссию из настроек один раз
        $commissionPercent = \App\Models\Setting::get('percent_for_sales', 20);
        // Цена с комиссией для пользователя
        $priceWithCommission = $event->price * (1 + ($commissionPercent / 100));
        
        $photoData = [];

        foreach ($photos as $photo) {
            if (!$photo->original_name) {
                LogHelper::warning("EventController::createEventInfoJson: Photo without original_name", [
                    'event_id' => $event->id,
                    'photo_id' => $photo->id
                ]);
                continue; // Пропускаем фото без имени
            }
            
            // Проверяем существование файла на хосте (для валидации)
            $filePath = storage_path('app/public/' . $photo->original_path);
            $fileExists = file_exists($filePath);
            $fileSize = $fileExists ? filesize($filePath) : 0;
            
            LogHelper::debug("EventController::createEventInfoJson: Processing photo", [
                'event_id' => $event->id,
                'photo_id' => $photo->id,
                'original_name' => $photo->original_name,
                'original_path' => $photo->original_path,
                'file_path' => $filePath,
                'file_exists' => $fileExists,
                'file_size' => $fileSize
            ]);
            
            // ВАЖНО: Сохраняем только относительный путь для работы в Docker контейнере
            // Абсолютный путь хоста не нужен, так как Celery работает в контейнере
            // где путь должен быть /var/www/html/storage/app/public/...
            $relativePath = $photo->original_path;
            
            // Путь для Docker контейнера (Celery будет использовать этот путь)
            $dockerPath = '/var/www/html/storage/app/public/' . $relativePath;
            
            // Цена фотографии с комиссией (используем цену фото или цену события)
            $photoPriceWithCommission = ($photo->price ?: $event->price) * (1 + ($commissionPercent / 100));
            
            $photoData[$photo->original_name] = [
                'id' => $photo->id,
                'size' => $fileSize,
                'ext' => pathinfo($photo->original_name, PATHINFO_EXTENSION),
                'filename' => pathinfo($photo->original_name, PATHINFO_FILENAME),
                'relative_path' => $relativePath, // Относительный путь (events/{event_id}/upload/filename.jpg)
                'docker_path' => $dockerPath,    // Путь для Docker контейнера
                'price' => $photoPriceWithCommission, // Цена с комиссией
            ];
        }
        
        $eventInfo = [
            'event_id' => $event->id,
            'author_id' => $event->author_id,
            'price' => $priceWithCommission, // Цена с комиссией для пользователя
            'base_price' => $event->price, // Базовая цена без комиссии
            'commission_percent' => $commissionPercent,
            'photo_count' => $photos->count(),
            'analyze' => [
                'timeline' => $analyses['timeline'] ?? false,
                'remove_exif' => $analyses['remove_exif'] ?? true, // Всегда true
                'watermark' => $analyses['watermark'] ?? true, // Всегда true
                'face_search' => $analyses['face_search'] ?? false,
                'number_search' => $analyses['number_search'] ?? false,
            ],
            'photo' => $photoData,
        ];

        // Создаем папку для события если её нет
        $eventPath = storage_path('app/public/events/' . $event->id);
        if (!file_exists($eventPath)) {
            $created = @mkdir($eventPath, 0755, true);
            if (!$created && !is_dir($eventPath)) {
                LogHelper::error("EventController::createEventInfoJson: Failed to create event directory", [
                    'event_id' => $event->id,
                    'path' => $eventPath
                ]);
                throw new \Exception("Не удалось создать директорию события: {$eventPath}");
            }
        }
        
        // Создаем подпапки
        $uploadPath = $eventPath . '/upload';
        $originalPath = $eventPath . '/original_photo';
        $customPath = $eventPath . '/custom_photo';
        
        foreach ([$uploadPath, $originalPath, $customPath] as $path) {
            if (!file_exists($path)) {
                $created = @mkdir($path, 0755, true);
                if (!$created && !is_dir($path)) {
                    LogHelper::error("EventController::createEventInfoJson: Failed to create subdirectory", [
                        'event_id' => $event->id,
                        'path' => $path
                    ]);
                }
            }
        }

        // Сохраняем event_info.json
        $jsonPath = $eventPath . '/event_info.json';
        
        LogHelper::debug("EventController::createEventInfoJson: Saving JSON", [
            'event_id' => $event->id,
            'json_path' => $jsonPath,
            'event_info' => $eventInfo
        ]);
        
        $jsonSaved = file_put_contents(
            $jsonPath,
            json_encode($eventInfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
        
        if ($jsonSaved === false) {
            LogHelper::error("EventController::createEventInfoJson: Failed to save JSON", [
                'event_id' => $event->id,
                'json_path' => $jsonPath
            ]);
            throw new \Exception("Не удалось сохранить event_info.json");
        }
        
        chmod($jsonPath, 0644);
        
        LogHelper::info("EventController::createEventInfoJson: JSON created successfully", [
            'event_id' => $event->id,
            'path' => $jsonPath,
            'photo_count' => $photos->count(),
            'file_size' => filesize($jsonPath),
            'file_exists' => file_exists($jsonPath)
        ]);
    }

    /**
     * Обработать обложку события через FastAPI
     */
    private function processEventCoverViaFastAPI(Event $event, string $tempCoverPath, string $outputPath): void
    {
        \Log::info("EventController::processEventCoverViaFastAPI: Starting", [
            'event_id' => $event->id,
            'temp_path' => $tempCoverPath,
            'output_path' => $outputPath
        ]);

        try {
            // Проверяем доступность FastAPI
            if (!$this->processingService->getApiClient()->healthCheck()) {
                throw new \Exception("FastAPI недоступен");
            }

            // Определяем путь к storage для FastAPI (в Docker контейнере)
            // FastAPI имеет доступ к /var/www/html/storage через volume
            $fastapiStoragePath = '/var/www/html/storage/app/public/' . str_replace(storage_path('app/public/'), '', $outputPath);
            
            \Log::debug("EventController::processEventCoverViaFastAPI: Paths", [
                'local_path' => $outputPath,
                'fastapi_path' => $fastapiStoragePath,
                'relative_path' => str_replace(storage_path('app/public/'), '', $outputPath)
            ]);
            
            // Отправляем запрос в FastAPI
            $response = \Illuminate\Support\Facades\Http::timeout(60)
                ->attach('cover_file', file_get_contents($tempCoverPath), basename($tempCoverPath))
                ->post(config('services.fastapi.url') . '/api/v1/events/' . $event->id . '/process-cover', [
                    'title' => $event->title,
                    'city' => $event->city,
                    'date' => $event->date->format('d.m.Y'),
                    'storage_path' => $fastapiStoragePath,
                ]);

            if (!$response->successful()) {
                throw new \Exception("FastAPI error: " . $response->body());
            }

            $result = $response->json();
            
            \Log::info("EventController::processEventCoverViaFastAPI: Success", [
                'event_id' => $event->id,
                'result' => $result
            ]);

        } catch (\Exception $e) {
            \Log::error("EventController::processEventCoverViaFastAPI: Error", [
                'event_id' => $event->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Обработать обложку события (наложение логотипа и текста) - DEPRECATED, используйте processEventCoverViaFastAPI
     */
    private function processEventCover(Event $event, string $coverPath): void
    {
        LogHelper::info("EventController::processEventCover: Starting", [
            'event_id' => $event->id,
            'cover_path' => $coverPath,
            'file_exists' => file_exists($coverPath)
        ]);

        try {
            if (!file_exists($coverPath)) {
                throw new \Exception("Cover file does not exist: {$coverPath}");
            }

            $manager = new \Intervention\Image\ImageManager(new \Intervention\Image\Drivers\Gd\Driver());
            LogHelper::debug("EventController::processEventCover: ImageManager created", [
                'event_id' => $event->id
            ]);
            
            $image = $manager->read($coverPath);
            LogHelper::debug("EventController::processEventCover: Image read", [
                'event_id' => $event->id,
                'width' => $image->width(),
                'height' => $image->height()
            ]);

            // Затемнение изображения (30%) - используем brightness для затемнения
            $image->brightness(-30);

            // Подготовка текста
            $text = $event->title . "\n" . $event->city . ", " . $event->date->format('d.m.Y');
            
            // Размер шрифта (примерно 5% от высоты изображения)
            $fontSize = max(24, (int)($image->height() * 0.05));
            
            // Пытаемся найти системный шрифт
            $fontPath = $this->getSystemFont();
            
            if ($fontPath && file_exists($fontPath)) {
                // Используем TTF шрифт
                $image->text($text, $image->width() / 2, $image->height() / 2, function ($font) use ($fontSize, $fontPath) {
                    $font->file($fontPath);
                    $font->size($fontSize);
                    $font->color('#ffffff');
                    $font->align('center');
                    $font->valign('middle');
                });
            } else {
                // Fallback - используем встроенный шрифт
                $image->text($text, $image->width() / 2, $image->height() / 2, function ($font) use ($fontSize) {
                    $font->size($fontSize);
                    $font->color('#ffffff');
                    $font->align('center');
                    $font->valign('middle');
                });
            }

            // Наложение логотипа (если есть)
            $logoPath = public_path('images/logo.png');
            if (file_exists($logoPath)) {
                try {
                    $logo = $manager->read($logoPath);
                    // Масштабируем логотип до 10% от размера изображения
                    $logoSize = (int)(min($image->width(), $image->height()) * 0.1);
                    $logo->scale($logoSize, $logoSize);
                    // Размещаем в правом нижнем углу с отступом
                    $image->place($logo, 'bottom-right', 10, 10);
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::warning("Could not add logo to cover", [
                        'event_id' => $event->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // Сохраняем обработанную обложку
            LogHelper::debug("EventController::processEventCover: Saving image", [
                'event_id' => $event->id,
                'cover_path' => $coverPath,
                'directory_exists' => file_exists(dirname($coverPath)),
                'is_writable' => is_writable(dirname($coverPath))
            ]);
            
            // Убеждаемся, что директория существует и доступна для записи
            $coverDir = dirname($coverPath);
            if (!file_exists($coverDir)) {
                mkdir($coverDir, 0755, true);
            }
            
            if (!is_writable($coverDir)) {
                chmod($coverDir, 0755);
            }
            
            // Сохраняем изображение
            $saved = $image->save($coverPath);
            
            if (!$saved || !file_exists($coverPath)) {
                throw new \Exception("Не удалось сохранить обработанную обложку в: {$coverPath}");
            }
            
            // Устанавливаем права доступа
            chmod($coverPath, 0644);
            
            LogHelper::info("EventController::processEventCover: Cover processed successfully", [
                'event_id' => $event->id,
                'cover_path' => $coverPath,
                'file_exists_after' => file_exists($coverPath),
                'file_size' => file_exists($coverPath) ? filesize($coverPath) : 0,
                'is_readable' => is_readable($coverPath)
            ]);
        } catch (\Exception $e) {
            LogHelper::error("EventController::processEventCover: Exception", [
                'event_id' => $event->id,
                'cover_path' => $coverPath,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            throw $e;
        }
    }

    /**
     * Получить путь к системному шрифту
     */
    private function getSystemFont(): ?string
    {
        $possiblePaths = [
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
            '/System/Library/Fonts/Helvetica.ttc',
            '/Windows/Fonts/arial.ttf',
            public_path('fonts/arial.ttf'),
            public_path('fonts/DejaVuSans-Bold.ttf'),
        ];

        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        return null;
    }
}
