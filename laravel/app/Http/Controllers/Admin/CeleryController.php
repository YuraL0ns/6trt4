<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CeleryTask;
use App\Models\Event;
use App\Services\PhotoProcessingService;
use App\Http\Client\FastApiClient;
use Illuminate\Http\Request;

class CeleryController extends Controller
{
    protected PhotoProcessingService $processingService;
    protected FastApiClient $fastApiClient;

    public function __construct(PhotoProcessingService $processingService, FastApiClient $fastApiClient)
    {
        $this->middleware('auth');
        $this->middleware('group:admin');
        $this->processingService = $processingService;
        $this->fastApiClient = $fastApiClient;
    }

    /**
     * Консоль Celery задач
     */
    public function index(Request $request)
    {
        // Обновление по запросу (polling)
        if ($request->ajax()) {
            $events = Event::whereHas('celeryTasks')
                ->with(['celeryTasks' => function($query) {
                    $query->orderBy('created_at', 'desc');
                }])
                ->orderBy('created_at', 'desc')
                ->limit(50)
                ->get();

            // Обновляем статусы задач на основе event_info.json
            foreach ($events as $event) {
                foreach ($event->celeryTasks as $task) {
                    if ($task->event_id && $task->task_id) {
                        try {
                            // Обновляем статус задачи через Celery
                            $this->processingService->updateTaskStatus($task->task_id);
                            
                            // Также обновляем на основе event_info.json
                            $eventInfo = $this->fastApiClient->getEventInfo($task->event_id);
                            if ($eventInfo) {
                                $this->updateTaskFromEventInfo($task, $eventInfo);
                            }
                        } catch (\Exception $e) {
                            \Log::warning("CeleryController::index: Failed to update task", [
                                'task_id' => $task->id,
                                'error' => $e->getMessage()
                            ]);
                        }
                    }
                }
            }

            // Перезагружаем события после обновления
            $events = Event::whereHas('celeryTasks')
                ->with(['celeryTasks' => function($query) {
                    $query->orderBy('created_at', 'desc');
                }])
                ->orderBy('created_at', 'desc')
                ->limit(50)
                ->get();

            return response()->json($events);
        }

        // Группируем задачи по событиям
        $events = Event::whereHas('celeryTasks')
            ->with(['celeryTasks' => function($query) {
                $query->orderBy('created_at', 'desc');
            }])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        // Убеждаемся, что связь загружена для всех событий и всегда возвращает коллекцию
        foreach ($events as $event) {
            if (!$event->relationLoaded('celeryTasks')) {
                $event->load('celeryTasks');
            }
            // Гарантируем, что celeryTasks всегда коллекция
            if ($event->celeryTasks === null) {
                $event->setRelation('celeryTasks', collect());
            } elseif (!($event->celeryTasks instanceof \Illuminate\Support\Collection)) {
                $event->setRelation('celeryTasks', collect($event->celeryTasks));
            }
        }

        return view('admin.celery.index', compact('events'));
    }

    /**
     * Просмотр задач конкретного события
     */
    public function show(string $eventId)
    {
        $event = Event::with(['celeryTasks', 'author', 'photos'])
            ->findOrFail($eventId);

        // Обновляем статусы задач
        foreach ($event->celeryTasks as $task) {
            if ($task->event_id && $task->task_id) {
                try {
                    $this->processingService->updateTaskStatus($task->task_id);
                    $eventInfo = $this->fastApiClient->getEventInfo($task->event_id);
                    if ($eventInfo) {
                        $this->updateTaskFromEventInfo($task, $eventInfo);
                    }
                } catch (\Exception $e) {
                    \Log::warning("CeleryController::show: Failed to update task", [
                        'task_id' => $task->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }

        // Перезагружаем событие после обновления
        $event->refresh();
        $event->load(['celeryTasks', 'author', 'photos']);

        // Получаем информацию о типах анализов
        // ВАЖНО: timeline временно отключен
        $analysisTypes = [
            'remove_exif' => ['name' => 'Удаление EXIF', 'key' => 'analyze_removeexif'],
            'watermark' => ['name' => 'Водяной знак', 'key' => 'analyze_watermark'],
            'face_search' => ['name' => 'Поиск лиц', 'key' => 'analyze_facesearch'],
            'number_search' => ['name' => 'Поиск номеров', 'key' => 'analyze_numbersearch']
        ];

        // Получаем event_info для детальной информации
        $eventInfo = null;
        try {
            $eventInfo = $this->fastApiClient->getEventInfo($event->id);
        } catch (\Exception $e) {
            \Log::warning("CeleryController::show: Failed to get event info", [
                'event_id' => $event->id,
                'error' => $e->getMessage()
            ]);
        }

        return view('admin.celery.show', compact('event', 'analysisTypes', 'eventInfo'));
    }

    /**
     * Перезапустить задачи для события
     */
    public function restart(Request $request, string $eventId)
    {
        $event = Event::with('celeryTasks')->findOrFail($eventId);
        
        try {
            // Получаем активные анализы из event_info.json
            $eventInfo = $this->fastApiClient->getEventInfo($event->id);
            if (!$eventInfo) {
                return back()->with('error', 'Не удалось получить информацию о событии');
            }
            
            // Определяем активные анализы
            $analyses = [
                'timeline' => isset($eventInfo['analyze_timeline']) && count($eventInfo['analyze_timeline']) > 0,
                'remove_exif' => isset($eventInfo['analyze_removeexif']) && count($eventInfo['analyze_removeexif']) > 0,
                'watermark' => isset($eventInfo['analyze_watermark']) && count($eventInfo['analyze_watermark']) > 0,
                'face_search' => isset($eventInfo['analyze_facesearch']) && count($eventInfo['analyze_facesearch']) > 0,
                'number_search' => isset($eventInfo['analyze_numbersearch']) && count($eventInfo['analyze_numbersearch']) > 0,
            ];
            
            // Запускаем обработку заново
            $this->processingService->processEventPhotos($event, $analyses, $event->price);
            
            // Логируем перезапуск
            \Log::info("CeleryController::restart: Tasks restarted for event", [
                'event_id' => $event->id,
                'admin_id' => auth()->id(),
                'analyses' => $analyses
            ]);
            
            return back()->with('success', 'Задачи перезапущены');
        } catch (\Exception $e) {
            \Log::error("CeleryController::restart: Error restarting tasks", [
                'event_id' => $event->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return back()->with('error', 'Ошибка при перезапуске задач: ' . $e->getMessage());
        }
    }

    /**
     * Получить логи задачи
     */
    public function logs(string $eventId)
    {
        $event = Event::findOrFail($eventId);
        
        try {
            // Получаем логи из Celery или event_info.json
            $eventInfo = $this->fastApiClient->getEventInfo($event->id);
            
            // Получаем задачи события
            $tasks = CeleryTask::where('event_id', $event->id)
                ->orderBy('created_at', 'desc')
                ->get();
            
            // Формируем логи
            $logs = [];
            foreach ($tasks as $task) {
                $taskLog = [
                    'task_id' => $task->id,
                    'task_type' => $task->task_type,
                    'task_type_name' => $this->getTaskTypeName($task->task_type),
                    'status' => $task->status,
                    'progress' => $task->progress,
                    'created_at' => $task->created_at->format('Y-m-d H:i:s'),
                    'updated_at' => $task->updated_at->format('Y-m-d H:i:s'),
                    'error_message' => $task->error_message,
                    'celery_task_id' => $task->task_id,
                ];
                
                // Получаем статус из Celery, если доступен
                if ($task->task_id) {
                    try {
                        $celeryStatus = $this->fastApiClient->getTaskStatus($task->task_id);
                        $taskLog['celery_state'] = $celeryStatus['state'] ?? null;
                        $taskLog['celery_status'] = $celeryStatus['status'] ?? null;
                        if (isset($celeryStatus['error'])) {
                            $taskLog['celery_error'] = $celeryStatus['error'];
                        }
                    } catch (\Exception $e) {
                        $taskLog['celery_error'] = 'Не удалось получить статус из Celery: ' . $e->getMessage();
                    }
                }
                
                // Добавляем детальную информацию из event_info.json
                if ($eventInfo) {
                    $analysisKey = $this->getAnalysisKey($task->task_type);
                    if ($analysisKey && isset($eventInfo[$analysisKey])) {
                        $analysisData = $eventInfo[$analysisKey];
                        $completed = count(array_filter($analysisData, function($item) {
                            $status = isset($item['status']) ? strtolower(trim($item['status'])) : '';
                            return $status === 'ready';
                        }));
                        $total = count($analysisData);
                        $taskLog['event_info'] = [
                            'completed' => $completed,
                            'total' => $total,
                            'last_updated' => !empty($analysisData) ? end($analysisData)['updated_at'] ?? null : null,
                        ];
                    }
                }
                
                $logs[] = $taskLog;
            }
            
            // Добавляем общую информацию из event_info.json
            if ($eventInfo) {
                $logs[] = [
                    'source' => 'event_info.json',
                    'photo_count' => $eventInfo['photo_count'] ?? 0,
                    'last_updated' => now()->format('Y-m-d H:i:s'),
                ];
            }
            
            return response()->json([
                'success' => true,
                'logs' => $logs,
                'event_title' => $event->title,
                'event_id' => $event->id,
            ]);
        } catch (\Exception $e) {
            \Log::error("CeleryController::logs: Error getting logs", [
                'event_id' => $event->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Ошибка при загрузке логов: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Перезапустить отдельную задачу
     */
    public function restartTask(Request $request, string $taskId)
    {
        $task = CeleryTask::findOrFail($taskId);
        $event = $task->event;
        
        try {
            // Сначала сбрасываем статус задачи на pending и прогресс на 0
            $task->update([
                'status' => 'pending',
                'progress' => 0,
                'error_message' => null,
                'task_id' => null, // Сбросим celery_task_id, он будет обновлен при запуске
            ]);
            
            \Log::info("CeleryController::restartTask: Task reset", [
                'task_id' => $task->id,
                'task_type' => $task->task_type,
                'event_id' => $event->id,
            ]);
            
            // Получаем активные анализы из event_info.json
            $eventInfo = $this->fastApiClient->getEventInfo($event->id);
            if (!$eventInfo) {
                return back()->with('error', 'Не удалось получить информацию о событии');
            }
            
            // Определяем, какой анализ нужно перезапустить
            // ВАЖНО: remove_exif и watermark всегда должны быть включены для корректной обработки
            // Но при перезапуске отдельной задачи мы запускаем только её, остальные отключаем
            $analyses = [
                'timeline' => false,
                'remove_exif' => false, // Отключаем, если не перезапускаем
                'watermark' => false,   // Отключаем, если не перезапускаем
                'face_search' => false,
                'number_search' => false,
            ];
            
            // Активируем нужный тип анализа
            $taskTypeKey = $task->task_type;
            if (isset($analyses[$taskTypeKey])) {
                $analyses[$taskTypeKey] = true;
            } else {
                // Если тип задачи не найден в массиве, пробуем напрямую
                $analyses[$taskTypeKey] = true;
            }
            
            // ВАЖНО: Для remove_exif и watermark всегда включаем их, так как они нужны для обработки
            // Но если перезапускаем другую задачу, они тоже должны быть включены
            if ($taskTypeKey !== 'remove_exif' && $taskTypeKey !== 'watermark') {
                // Если перезапускаем не remove_exif и не watermark, включаем их тоже
                $analyses['remove_exif'] = true;
                $analyses['watermark'] = true;
            }
            
            \Log::info("CeleryController::restartTask: Analyses configuration", [
                'task_type' => $taskTypeKey,
                'analyses' => $analyses,
            ]);
            
            \Log::info("CeleryController::restartTask: Starting processEventPhotos", [
                'task_id' => $task->id,
                'task_type' => $task->task_type,
                'event_id' => $event->id,
                'analyses' => $analyses,
            ]);
            
            // Запускаем обработку заново
            $this->processingService->processEventPhotos($event, $analyses, $event->price);
            
            // Получаем обновленную задачу с новым celery_task_id
            $updatedTask = CeleryTask::where('event_id', $event->id)
                ->where('task_type', $task->task_type)
                ->first();
            
            if ($updatedTask) {
                // Явно обновляем задачу, чтобы убедиться, что все поля обновлены
                $updatedTask->refresh();
                
                \Log::info("CeleryController::restartTask: Task updated with new celery_task_id", [
                    'task_id' => $updatedTask->id,
                    'celery_task_id' => $updatedTask->task_id,
                    'status' => $updatedTask->status,
                    'progress' => $updatedTask->progress,
                ]);
                
                // Если task_id все еще null, пытаемся получить из FastAPI ответа
                if (!$updatedTask->task_id) {
                    \Log::warning("CeleryController::restartTask: celery_task_id is null after restart", [
                        'task_id' => $updatedTask->id,
                        'event_id' => $event->id,
                    ]);
                    
                    // Пытаемся получить task_id из последнего вызова FastAPI
                    // Это может произойти, если updateOrCreate не обновил поле
                    $eventInfoAfter = $this->fastApiClient->getEventInfo($event->id);
                    if ($eventInfoAfter && isset($eventInfoAfter['last_task_id'])) {
                        $updatedTask->update(['task_id' => $eventInfoAfter['last_task_id']]);
                    } else {
                        // Если не получилось, проверяем последние задачи события
                        $lastTask = CeleryTask::where('event_id', $event->id)
                            ->whereNotNull('task_id')
                            ->orderBy('updated_at', 'desc')
                            ->first();
                        
                        if ($lastTask && $lastTask->task_id) {
                            $updatedTask->update(['task_id' => $lastTask->task_id]);
                            \Log::info("CeleryController::restartTask: Using task_id from last task", [
                                'task_id' => $updatedTask->id,
                                'celery_task_id' => $lastTask->task_id,
                            ]);
                        }
                    }
                }
                
                // Проверяем, что задача действительно запустилась
                if ($updatedTask->task_id && $updatedTask->status === 'pending') {
                    // Задача успешно перезапущена
                    \Log::info("CeleryController::restartTask: Task restarted successfully", [
                        'task_id' => $updatedTask->id,
                        'celery_task_id' => $updatedTask->task_id,
                        'status' => $updatedTask->status,
                    ]);
                } else {
                    \Log::warning("CeleryController::restartTask: Task may not have restarted properly", [
                        'task_id' => $updatedTask->id,
                        'celery_task_id' => $updatedTask->task_id,
                        'status' => $updatedTask->status,
                    ]);
                }
            } else {
                \Log::error("CeleryController::restartTask: Updated task not found after restart", [
                    'event_id' => $event->id,
                    'task_type' => $task->task_type,
                    'original_task_id' => $task->id,
                ]);
                
                return back()->with('error', 'Задача не найдена после перезапуска. Проверьте логи.');
            }
            
            // Логируем перезапуск
            \Log::info("CeleryController::restartTask: Task restart completed", [
                'task_id' => $task->id,
                'updated_task_id' => $updatedTask->id,
                'task_type' => $task->task_type,
                'event_id' => $event->id,
                'celery_task_id' => $updatedTask->task_id,
                'admin_id' => auth()->id(),
            ]);
            
            return back()->with('success', 'Задача "' . $this->getTaskTypeName($task->task_type) . '" перезапущена. Celery Task ID: ' . substr($updatedTask->task_id ?? 'N/A', 0, 20) . '...');
        } catch (\Exception $e) {
            \Log::error("CeleryController::restartTask: Error restarting task", [
                'task_id' => $task->id,
                'event_id' => $event->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Обновляем задачу с ошибкой
            $task->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
            
            return back()->with('error', 'Ошибка при перезапуске задачи: ' . $e->getMessage());
        }
    }

    /**
     * Получить логи отдельной задачи
     */
    public function getTaskLog(string $taskId)
    {
        $task = CeleryTask::with('event')->findOrFail($taskId);
        
        try {
            $log = [
                'task_id' => $task->id,
                'task_type' => $task->task_type,
                'task_type_name' => $this->getTaskTypeName($task->task_type),
                'status' => $task->status,
                'progress' => $task->progress,
                'created_at' => $task->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $task->updated_at->format('Y-m-d H:i:s'),
                'error_message' => $task->error_message,
                'celery_task_id' => $task->task_id,
                'event_id' => $task->event_id,
                'event_title' => $task->event->title,
            ];
            
            // Получаем статус из Celery, если доступен
            if ($task->task_id) {
                try {
                    $celeryStatus = $this->fastApiClient->getTaskStatus($task->task_id);
                    $log['celery_state'] = $celeryStatus['state'] ?? null;
                    $log['celery_status'] = $celeryStatus['status'] ?? null;
                    if (isset($celeryStatus['error'])) {
                        $log['celery_error'] = $celeryStatus['error'];
                    }
                    if (isset($celeryStatus['result'])) {
                        $log['celery_result'] = $celeryStatus['result'];
                    }
                } catch (\Exception $e) {
                    $log['celery_error'] = 'Не удалось получить статус из Celery: ' . $e->getMessage();
                }
            }
            
            // Получаем детальную информацию из event_info.json
            $eventInfo = $this->fastApiClient->getEventInfo($task->event_id);
            if ($eventInfo) {
                $analysisKey = $this->getAnalysisKey($task->task_type);
                if ($analysisKey && isset($eventInfo[$analysisKey])) {
                    $analysisData = $eventInfo[$analysisKey];
                    $completed = count(array_filter($analysisData, function($item) {
                        $status = isset($item['status']) ? strtolower(trim($item['status'])) : '';
                        return $status === 'ready';
                    }));
                    $total = count($analysisData);
                    $log['event_info'] = [
                        'completed' => $completed,
                        'total' => $total,
                        'details' => array_slice($analysisData, 0, 10), // Первые 10 записей для примера
                    ];
                }
            }
            
            return response()->json([
                'success' => true,
                'log' => $log,
            ]);
        } catch (\Exception $e) {
            \Log::error("CeleryController::getTaskLog: Error getting task log", [
                'task_id' => $taskId,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Получить название типа задачи
     */
    protected function getTaskTypeName(string $taskType): string
    {
        $names = [
            'timeline' => 'Timeline',
            'remove_exif' => 'Удаление EXIF',
            'watermark' => 'Водяной знак',
            'face_search' => 'Поиск лиц',
            'number_search' => 'Поиск номеров',
        ];
        
        return $names[$taskType] ?? $taskType;
    }

    /**
     * Получить ключ анализа из event_info.json
     */
    protected function getAnalysisKey(string $taskType): ?string
    {
        $keys = [
            'timeline' => 'analyze_timeline',
            'remove_exif' => 'analyze_removeexif',
            'watermark' => 'analyze_watermark',
            'face_search' => 'analyze_facesearch',
            'number_search' => 'analyze_numbersearch',
        ];
        
        return $keys[$taskType] ?? null;
    }

    /**
     * Удалить задачу
     */
    public function deleteTask(string $taskId)
    {
        $task = CeleryTask::findOrFail($taskId);
        $eventId = $task->event_id;
        
        try {
            // Удаляем задачу из базы данных
            $task->delete();
            
            \Log::info("CeleryController::deleteTask: Task deleted", [
                'task_id' => $taskId,
                'task_type' => $task->task_type,
                'event_id' => $eventId,
                'admin_id' => auth()->id(),
            ]);
            
            return back()->with('success', 'Задача удалена');
        } catch (\Exception $e) {
            \Log::error("CeleryController::deleteTask: Error deleting task", [
                'task_id' => $taskId,
                'event_id' => $eventId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return back()->with('error', 'Ошибка при удалении задачи: ' . $e->getMessage());
        }
    }

    /**
     * Запустить отдельную задачу для события
     */
    public function startTask(Request $request, string $eventId)
    {
        $event = Event::findOrFail($eventId);
        
        $request->validate([
            'task_type' => 'required|in:remove_exif,watermark,face_search,number_search' // timeline временно отключен
        ]);
        
        $taskType = $request->input('task_type');
        
        try {
            // Получаем event_info.json для определения активных анализов
            $eventInfo = $this->fastApiClient->getEventInfo($event->id);
            if (!$eventInfo) {
                return back()->with('error', 'Не удалось получить информацию о событии');
            }
            
            // Определяем, какие анализы нужно запустить
            // Для remove_exif и watermark всегда true, для остальных - только выбранный
            // Timeline временно отключен
            $analyses = [
                // 'timeline' => $taskType === 'timeline', // Timeline временно отключен
                'remove_exif' => $taskType === 'remove_exif' || $taskType === 'watermark', // remove_exif нужен для watermark
                'watermark' => $taskType === 'watermark',
                'face_search' => $taskType === 'face_search',
                'number_search' => $taskType === 'number_search',
            ];
            
            // Запускаем обработку
            $this->processingService->processEventPhotos($event, $analyses, $event->price);
            
            \Log::info("CeleryController::startTask: Task started", [
                'event_id' => $event->id,
                'task_type' => $taskType,
                'admin_id' => auth()->id(),
                'analyses' => $analyses
            ]);
            
            return back()->with('success', 'Задача "' . $this->getTaskTypeName($taskType) . '" запущена');
        } catch (\Exception $e) {
            \Log::error("CeleryController::startTask: Error starting task", [
                'event_id' => $event->id,
                'task_type' => $taskType,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return back()->with('error', 'Ошибка при запуске задачи: ' . $e->getMessage());
        }
    }

    /**
     * Обновить задачу на основе event_info.json
     * ВАЖНО: Использует ту же логику, что и updateAllTasksFromEventInfo в PhotoProcessingService
     * для консистентности обновления статусов
     */
    protected function updateTaskFromEventInfo(CeleryTask $task, array $eventInfo): void
    {
        $analysisTypes = [
            'timeline' => 'analyze_timeline',
            'remove_exif' => 'analyze_removeexif',
            'watermark' => 'analyze_watermark',
            'face_search' => 'analyze_facesearch',
            'number_search' => 'analyze_numbersearch'
        ];

        $jsonKey = $analysisTypes[$task->task_type] ?? null;
        if (!$jsonKey || !isset($eventInfo[$jsonKey])) {
            \Log::debug("CeleryController::updateTaskFromEventInfo: Section not found", [
                'task_type' => $task->task_type,
                'json_key' => $jsonKey
            ]);
            return;
        }

        $sectionData = $eventInfo[$jsonKey];
        $totalPhotos = $eventInfo['photo_count'] ?? count($eventInfo['photo'] ?? []);

        // Подсчитываем обработанные фотографии (учитываем и ready, и error)
        $readyCount = 0;
        $errorCount = 0;
        $processingCount = 0;

        foreach ($sectionData as $item) {
            $itemStatus = isset($item['status']) ? strtolower(trim($item['status'])) : '';
            if ($itemStatus === 'ready') {
                $readyCount++;
            } elseif ($itemStatus === 'error') {
                $errorCount++;
            } elseif ($itemStatus === 'processing') {
                $processingCount++;
            }
        }

        // Вычисляем прогресс (учитываем и успешно обработанные, и с ошибками)
        $progress = $totalPhotos > 0 ? intval((($readyCount + $errorCount) / $totalPhotos) * 100) : 0;

        // Определяем статус на основе прогресса
        // Задача считается завершенной только если обработано >= 95% фотографий
        // Это позволяет учесть возможные ошибки на отдельных фотографиях
        $newStatus = 'pending';
        if ($totalPhotos > 0) {
            $completionPercent = (($readyCount + $errorCount) / $totalPhotos) * 100;
            if ($completionPercent >= 95) {
                $newStatus = 'completed';
            } elseif ($readyCount > 0 || $errorCount > 0 || $processingCount > 0) {
                $newStatus = 'processing';
            }
        }

        // Обновляем задачу только если статус или прогресс изменились
        $updateData = [];
        if ($task->status !== $newStatus) {
            $updateData['status'] = $newStatus;
        }
        if ($task->progress !== $progress) {
            $updateData['progress'] = $progress;
        }

        if (!empty($updateData)) {
            $task->update($updateData);
            \Log::info("CeleryController::updateTaskFromEventInfo: Task updated", [
                'task_id' => $task->id,
                'task_type' => $task->task_type,
                'old_status' => $task->getOriginal('status'),
                'new_status' => $newStatus,
                'old_progress' => $task->getOriginal('progress'),
                'new_progress' => $progress,
                'ready_count' => $readyCount,
                'error_count' => $errorCount,
                'total_photos' => $totalPhotos,
                'completion_percent' => $totalPhotos > 0 ? (($readyCount + $errorCount) / $totalPhotos) * 100 : 0
            ]);
        }
    }
}
