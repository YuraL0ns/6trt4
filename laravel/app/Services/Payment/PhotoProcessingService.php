<?php

namespace App\Services;

use App\Http\Client\FastApiClient;
use App\Models\Event;
use App\Models\Photo;
use App\Models\CeleryTask;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Helpers\LogHelper;
use Exception;

class PhotoProcessingService
{
    protected FastApiClient $apiClient;

    public function __construct(FastApiClient $apiClient)
    {
        $this->apiClient = $apiClient;
        \Log::info("PhotoProcessingService::__construct: Initialized");
    }

    /**
     * Получить клиент FastAPI
     */
    public function getApiClient(): FastApiClient
    {
        return $this->apiClient;
    }

    /**
     * Запустить обработку фотографий события
     */
    public function processEventPhotos(Event $event, array $analyses, float $price): void
    {
        \Log::info("PhotoProcessingService::processEventPhotos: Starting", [
            'event_id' => $event->id,
            'price' => $price,
            'analyses' => $analyses
        ]);
        
        LogHelper::info("PhotoProcessingService::processEventPhotos: Starting", [
            'event_id' => $event->id,
            'price' => $price,
            'analyses' => $analyses
        ]);

        try {
            // Обновляем цену события
            $event->update(['price' => $price]);
            LogHelper::debug("PhotoProcessingService::processEventPhotos: Event price updated", [
                'event_id' => $event->id,
                'price' => $price
            ]);

            // Запускаем анализ через FastAPI
            LogHelper::info("PhotoProcessingService::processEventPhotos: Calling FastAPI", [
                'event_id' => $event->id
            ]);
            $result = $this->apiClient->startEventAnalysis($event->id, $analyses);
            LogHelper::info("PhotoProcessingService::processEventPhotos: FastAPI response", [
                'event_id' => $event->id,
                'result' => $result
            ]);

            // Создаем записи о задачах Celery
            // ВАЖНО: remove_exif и watermark всегда должны быть включены
            $taskTypes = [
                'timeline' => 'timeline',
                'remove_exif' => 'remove_exif',
                'watermark' => 'watermark',
                'face_search' => 'face_search',
                'number_search' => 'number_search',
            ];

            // Убеждаемся, что remove_exif и watermark всегда включены
            $analyses['remove_exif'] = true;
            $analyses['watermark'] = true;

            LogHelper::info("PhotoProcessingService::processEventPhotos: Creating CeleryTasks", [
                'event_id' => $event->id,
                'analyses' => $analyses,
                'celery_task_id' => $result['task_id'] ?? null
            ]);

            foreach ($taskTypes as $key => $type) {
                // Проверяем, включен ли этот тип анализа
                $isEnabled = $analyses[$key] ?? false;
                
                LogHelper::debug("PhotoProcessingService::processEventPhotos: Processing task type", [
                    'event_id' => $event->id,
                    'task_type' => $type,
                    'key' => $key,
                    'is_enabled' => $isEnabled,
                    'analyses_value' => $analyses[$key] ?? 'not_set'
                ]);
                
                if ($isEnabled) {
                    // Используем updateOrCreate чтобы избежать дубликатов при повторном запуске
                    // Это позволяет создавать задачи для второго анализа, даже если первый еще выполняется
                    $task = CeleryTask::updateOrCreate(
                        [
                            'event_id' => $event->id,
                            'task_type' => $type,
                        ],
                        [
                            'task_id' => $result['task_id'] ?? null,
                            'status' => 'pending',
                            'progress' => 0,
                        ]
                    );
                    
                    LogHelper::info("PhotoProcessingService::processEventPhotos: CeleryTask created/updated", [
                        'event_id' => $event->id,
                        'task_id' => $task->id,
                        'celery_task_id' => $task->task_id,
                        'task_type' => $type,
                        'was_recently_created' => $task->wasRecentlyCreated,
                        'status' => $task->status,
                        'progress' => $task->progress
                    ]);
                } else {
                    LogHelper::debug("PhotoProcessingService::processEventPhotos: Task type disabled, skipping", [
                        'event_id' => $event->id,
                        'task_type' => $type,
                        'key' => $key
                    ]);
                }
            }

            // Обновляем статус события
            $event->update(['status' => 'processing']);
            LogHelper::info("PhotoProcessingService::processEventPhotos: Event status updated to processing", [
                'event_id' => $event->id
            ]);
        } catch (Exception $e) {
            \Log::error("PhotoProcessingService::processEventPhotos: Exception", [
                'event_id' => $event->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            LogHelper::error("PhotoProcessingService::processEventPhotos: Exception", [
                'event_id' => $event->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            throw $e;
        }
    }

    /**
     * Обновить статус задачи Celery
     */
    public function updateTaskStatus(string $taskId): void
    {
        \Log::debug("PhotoProcessingService::updateTaskStatus: Starting", [
            'task_id' => $taskId
        ]);
        
        try {
            $status = $this->apiClient->getTaskStatus($taskId);
            
            \Log::debug("PhotoProcessingService::updateTaskStatus: Status received", [
                'task_id' => $taskId,
                'status' => $status
            ]);

            // ВАЖНО: Все задачи ссылаются на одну Celery задачу process_event_photos
            // Поэтому обновляем ВСЕ задачи для этого события, а не только одну
            $tasks = CeleryTask::where('task_id', $taskId)->get();
            
            if ($tasks->count() > 0) {
                $event = $tasks->first()->event;
                
                // ВАЖНО: Обновляем статусы задач на основе РЕАЛЬНОГО прогресса из event_info.json,
                // а не только на основе статуса Celery задачи
                // Это критично, так как одна Celery задача выполняет все анализы последовательно
                $this->updateAllTasksFromEventInfo($event);
                
                // Обновляем информацию об ошибке для всех задач, если Celery задача завершилась с ошибкой
                if (in_array($status['state'], ['FAILURE'])) {
                    $errorMessage = null;
                    if (isset($status['error'])) {
                        $errorMessage = $status['error'];
                        if (isset($status['error_type'])) {
                            $errorMessage = "[" . $status['error_type'] . "] " . $errorMessage;
                        }
                        if (isset($status['traceback']) && !empty($status['traceback'])) {
                            $traceback = substr($status['traceback'], 0, 2000);
                            $errorMessage .= "\n\nTraceback:\n" . $traceback;
                        }
                    } elseif (isset($status['info'])) {
                        $errorMessage = is_string($status['info']) ? $status['info'] : json_encode($status['info']);
                    }
                    
                    if ($errorMessage) {
                        foreach ($tasks as $task) {
                            if (empty($task->error_message)) {
                                $task->update(['error_message' => $errorMessage]);
                            }
                        }
                    }
                }
                
                // Обновляем общий прогресс из Celery (если доступен)
                if (isset($status['progress']) && $status['progress'] > 0) {
                    foreach ($tasks as $task) {
                        // Обновляем прогресс только если он больше текущего (чтобы не уменьшать)
                        if ($task->progress < $status['progress']) {
                            $task->update(['progress' => $status['progress']]);
                        }
                    }
                }
                
                // Используем первую задачу для дальнейшей логики
                $task = $tasks->first();
                
                \Log::info("PhotoProcessingService::updateTaskStatus: Tasks updated from event_info.json", [
                    'task_id' => $taskId,
                    'event_id' => $event->id,
                    'celery_state' => $status['state'],
                    'tasks_count' => $tasks->count(),
                    'celery_progress' => $status['progress'] ?? 0
                ]);

                // Если задача завершена, обновляем статус события
                if (in_array($status['state'], ['SUCCESS', 'FAILURE'])) {
                    $event = $task->event;
                    if ($event) {
                        // Перезагружаем задачу для актуальных данных
                        $task->refresh();
                        
                        // Перезагружаем событие и задачи для актуальных данных
                        $event->refresh();
                        $event->load('celeryTasks');
                        
                        // Проверяем, все ли задачи завершены
                        // ВАЖНО: Не публикуем событие, если:
                        // 1. Нет задач вообще
                        // 2. Все задачи в статусе 'pending' (еще не запущены)
                        // 3. Задачи не имеют task_id (не были отправлены в Celery)
                        // 4. Не все задачи завершены
                        $tasks = $event->celeryTasks;
                        $hasTasks = $tasks->count() > 0;
                        $allCompleted = false;
                        
                        if ($hasTasks) {
                            // Проверяем, что все задачи либо завершены, либо провалились
                            // И что хотя бы одна задача была запущена (не все в pending)
                            $allInPending = $tasks->every(function ($t) {
                                return $t->status === 'pending';
                            });
                            
                            // Проверяем, что задачи имеют task_id (были отправлены в Celery)
                            $allHaveTaskId = $tasks->every(function ($t) {
                                return !empty($t->task_id);
                            });
                            
                            if (!$allInPending && $allHaveTaskId) {
                                // Не все в pending и все имеют task_id - проверяем завершение
                                // ВАЖНО: Проверяем реальный прогресс из event_info.json, а не только статус задачи
                                // Это критично, так как статусы задач обновляются на основе event_info.json
                                $allCompleted = $tasks->every(function ($t) use ($event) {
                                    // Проверяем статус задачи (должен быть 'completed' или 'failed')
                                    $statusCompleted = in_array($t->status, ['completed', 'failed']);
                                    
                                    if (!$statusCompleted) {
                                        \Log::debug("PhotoProcessingService::updateTaskStatus: Task not completed by status", [
                                            'task_type' => $t->task_type,
                                            'status' => $t->status,
                                            'progress' => $t->progress
                                        ]);
                                        return false;
                                    }
                                    
                                    // Дополнительная проверка: проверяем реальный прогресс из event_info.json
                                    // Это гарантирует, что задача действительно завершена, а не просто помечена как завершенная
                                    if ($t->status === 'completed') {
                                        try {
                                            $eventInfoPath = storage_path('app/public/events/' . $event->id . '/event_info.json');
                                            if (file_exists($eventInfoPath)) {
                                                $eventInfo = json_decode(file_get_contents($eventInfoPath), true);
                                                
                                                // Маппинг типов задач на секции в event_info.json
                                                $sectionMap = [
                                                    'timeline' => 'analyze_timeline',
                                                    'remove_exif' => 'analyze_removeexif',
                                                    'watermark' => 'analyze_watermark',
                                                    'face_search' => 'analyze_facesearch',
                                                    'number_search' => 'analyze_numbersearch',
                                                ];
                                                
                                                $sectionKey = $sectionMap[$t->task_type] ?? null;
                                                if ($sectionKey && isset($eventInfo[$sectionKey])) {
                                                    $sectionData = $eventInfo[$sectionKey];
                                                    $totalPhotos = $eventInfo['photo_count'] ?? count($eventInfo['photo'] ?? []);
                                                    
                                                    if ($totalPhotos > 0) {
                                                        // Проверяем, что все фотографии обработаны (статус 'ready' или 'error')
                                                        $readyCount = 0;
                                                        $errorCount = 0;
                                                        foreach ($sectionData as $item) {
                                                            $itemStatus = isset($item['status']) ? strtolower(trim($item['status'])) : '';
                                                            if ($itemStatus === 'ready') {
                                                                $readyCount++;
                                                            } elseif ($itemStatus === 'error') {
                                                                $errorCount++;
                                                            }
                                                        }
                                                        
                                                        $completionPercent = (($readyCount + $errorCount) / $totalPhotos) * 100;
                                                        
                                                        \Log::debug("PhotoProcessingService::updateTaskStatus: Task progress check", [
                                                            'task_type' => $t->task_type,
                                                            'section' => $sectionKey,
                                                            'ready_count' => $readyCount,
                                                            'error_count' => $errorCount,
                                                            'total_photos' => $totalPhotos,
                                                            'completion_percent' => $completionPercent,
                                                            'status_completed' => $statusCompleted
                                                        ]);
                                                        
                                                        // Задача считается завершенной только если обработано >= 95% фотографий
                                                        // Это позволяет учесть возможные ошибки на отдельных фотографиях
                                                        if ($completionPercent < 95) {
                                                            \Log::warning("PhotoProcessingService::updateTaskStatus: Task marked as completed but progress < 95%", [
                                                                'task_type' => $t->task_type,
                                                                'completion_percent' => $completionPercent,
                                                                'ready_count' => $readyCount,
                                                                'error_count' => $errorCount,
                                                                'total_photos' => $totalPhotos
                                                            ]);
                                                            return false; // Задача не завершена по факту
                                                        }
                                                    }
                                                } else {
                                                    \Log::warning("PhotoProcessingService::updateTaskStatus: Section not found in event_info.json", [
                                                        'task_type' => $t->task_type,
                                                        'section_key' => $sectionKey
                                                    ]);
                                                    // Если секция не найдена, полагаемся на статус задачи
                                                }
                                            } else {
                                                \Log::warning("PhotoProcessingService::updateTaskStatus: event_info.json not found", [
                                                    'event_id' => $event->id,
                                                    'path' => $eventInfoPath
                                                ]);
                                                // Если файл не найден, полагаемся на статус задачи
                                            }
                                        } catch (\Exception $e) {
                                            \Log::warning("PhotoProcessingService::updateTaskStatus: Error checking event_info.json progress", [
                                                'task_type' => $t->task_type,
                                                'error' => $e->getMessage()
                                            ]);
                                            // Если не удалось проверить, полагаемся на статус задачи
                                        }
                                    }
                                    
                                    return $statusCompleted;
                                });
                            } else {
                                if ($allInPending) {
                                    \Log::debug("PhotoProcessingService::updateTaskStatus: All tasks are pending, not publishing", [
                                        'event_id' => $event->id
                                    ]);
                                }
                                if (!$allHaveTaskId) {
                                    \Log::warning("PhotoProcessingService::updateTaskStatus: Some tasks don't have task_id, not publishing", [
                                        'event_id' => $event->id,
                                        'tasks_without_id' => $tasks->filter(function($t) { return empty($t->task_id); })->pluck('task_type')->toArray()
                                    ]);
                                }
                            }
                        }

                        \Log::info("PhotoProcessingService::updateTaskStatus: Checking if all tasks completed", [
                            'event_id' => $event->id,
                            'event_status' => $event->status,
                            'all_completed' => $allCompleted,
                            'tasks_count' => $event->celeryTasks->count(),
                            'tasks_statuses' => $event->celeryTasks->pluck('status')->toArray(),
                            'tasks_details' => $event->celeryTasks->map(function($t) {
                                return [
                                    'id' => $t->id,
                                    'task_id' => $t->task_id,
                                    'task_type' => $t->task_type,
                                    'status' => $t->status,
                                    'progress' => $t->progress
                                ];
                            })->toArray(),
                            'current_task_status' => $status['state']
                        ]);

                        if ($allCompleted && in_array($event->status, ['processing', 'draft'])) {
                            $previousStatus = $event->status;
                            $event->update(['status' => 'published']);
                            $event->refresh();
                            
                            \Log::info("PhotoProcessingService::updateTaskStatus: Event status updated to published", [
                                'event_id' => $event->id,
                                'previous_status' => $previousStatus,
                                'new_status' => $event->status,
                                'tasks_count' => $event->celeryTasks->count()
                            ]);
                        } else {
                            \Log::info("PhotoProcessingService::updateTaskStatus: Event status not updated", [
                                'event_id' => $event->id,
                                'event_status' => $event->status,
                                'all_completed' => $allCompleted,
                                'reason' => !$allCompleted ? 'Not all tasks completed' : 'Event status not in processing/draft'
                            ]);
                        }
                    }
                }
            } else {
                \Log::warning("PhotoProcessingService::updateTaskStatus: Task not found", [
                    'task_id' => $taskId
                ]);
            }
        } catch (Exception $e) {
            \Log::error("PhotoProcessingService::updateTaskStatus: Error", [
                'task_id' => $taskId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            Log::error("UpdateTaskStatus error", [
                'task_id' => $taskId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Поиск похожих фотографий по лицу
     */
    public function searchSimilarFaces(string $imagePath, ?string $eventId = null, float $threshold = 1.2): array
    {
        try {
            $result = $this->apiClient->searchByFace($imagePath, $eventId, $threshold);

            // Если задача запущена, ждем результата
            if (isset($result['task_id'])) {
                return [
                    'task_id' => $result['task_id'],
                    'status' => 'processing'
                ];
            }

            return $result;
        } catch (Exception $e) {
            Log::error("SearchSimilarFaces error", [
                'event_id' => $eventId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Поиск фотографий по номеру
     */
    public function searchByNumber(string $imagePath, ?string $eventId = null): array
    {
        try {
            $result = $this->apiClient->searchByNumber($imagePath, $eventId);

            if (isset($result['task_id'])) {
                return [
                    'task_id' => $result['task_id'],
                    'status' => 'processing'
                ];
            }

            return $result;
        } catch (Exception $e) {
            Log::error("SearchByNumber error", [
                'event_id' => $eventId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Обновить все задачи события на основе event_info.json
     * ВАЖНО: Это основной метод обновления статусов задач, так как все задачи
     * ссылаются на одну Celery задачу process_event_photos
     */
    protected function updateAllTasksFromEventInfo(Event $event): void
    {
        try {
            $eventInfoPath = storage_path('app/public/events/' . $event->id . '/event_info.json');
            
            if (!file_exists($eventInfoPath)) {
                \Log::debug("PhotoProcessingService::updateAllTasksFromEventInfo: event_info.json not found", [
                    'event_id' => $event->id,
                    'path' => $eventInfoPath
                ]);
                return;
            }
            
            $eventInfo = json_decode(file_get_contents($eventInfoPath), true);
            if (!$eventInfo) {
                \Log::warning("PhotoProcessingService::updateAllTasksFromEventInfo: Failed to parse event_info.json", [
                    'event_id' => $event->id
                ]);
                return;
            }
            
            // Маппинг типов задач на секции в event_info.json
            $sectionMap = [
                'timeline' => 'analyze_timeline',
                'remove_exif' => 'analyze_removeexif',
                'watermark' => 'analyze_watermark',
                'face_search' => 'analyze_facesearch',
                'number_search' => 'analyze_numbersearch',
            ];
            
            $totalPhotos = $eventInfo['photo_count'] ?? count($eventInfo['photo'] ?? []);
            
            // Обновляем каждую задачу на основе реального прогресса
            foreach ($event->celeryTasks as $task) {
                $sectionKey = $sectionMap[$task->task_type] ?? null;
                
                if (!$sectionKey || !isset($eventInfo[$sectionKey])) {
                    \Log::debug("PhotoProcessingService::updateAllTasksFromEventInfo: Section not found", [
                        'task_type' => $task->task_type,
                        'section_key' => $sectionKey
                    ]);
                    continue;
                }
                
                $sectionData = $eventInfo[$sectionKey];
                
                // Подсчитываем обработанные фотографии
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
                
                // Вычисляем прогресс
                $progress = $totalPhotos > 0 ? intval((($readyCount + $errorCount) / $totalPhotos) * 100) : 0;
                
                // Определяем статус на основе прогресса
                // Задача считается завершенной только если обработано >= 95% фотографий
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
                    \Log::info("PhotoProcessingService::updateAllTasksFromEventInfo: Task updated", [
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
        } catch (\Exception $e) {
            \Log::error("PhotoProcessingService::updateAllTasksFromEventInfo: Error", [
                'event_id' => $event->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Маппинг статуса задачи Celery
     */
    protected function mapTaskStatus(string $state): string
    {
        return match ($state) {
            'PENDING' => 'pending',
            'PROGRESS' => 'processing',
            'SUCCESS' => 'completed',
            'FAILURE' => 'failed',
            default => 'pending',
        };
    }
}


