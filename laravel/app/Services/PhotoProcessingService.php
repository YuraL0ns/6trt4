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
        // Убрано логирование из конструктора, так как оно может вызывать ошибки при инициализации
        // Логирование будет происходить в методах, когда это необходимо
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
            // ВАЖНО: timeline временно отключен
            $taskTypes = [
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
                            'error_message' => null, // Сбрасываем ошибки при перезапуске
                        ]
                    );
                    
                    // ВАЖНО: Если задача уже существовала, явно обновляем все поля
                    // updateOrCreate может не обновить все поля, если они не указаны во втором массиве
                    if (!$task->wasRecentlyCreated && $result['task_id']) {
                        $task->update([
                            'task_id' => $result['task_id'],
                            'status' => 'pending',
                            'progress' => 0,
                            'error_message' => null,
                        ]);
                        LogHelper::info("PhotoProcessingService::processEventPhotos: Existing task explicitly updated", [
                            'event_id' => $event->id,
                            'task_id' => $task->id,
                            'celery_task_id' => $task->task_id,
                            'task_type' => $type,
                        ]);
                    }
                    
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

            $task = CeleryTask::where('task_id', $taskId)->first();
            if ($task) {
                $mappedStatus = $this->mapTaskStatus($status['state']);
                $task->update([
                    'status' => $mappedStatus,
                    'progress' => $status['progress'] ?? 0,
                ]);
                
                \Log::info("PhotoProcessingService::updateTaskStatus: Task updated", [
                    'task_id' => $taskId,
                    'event_id' => $task->event_id,
                    'celery_state' => $status['state'],
                    'mapped_status' => $mappedStatus,
                    'progress' => $status['progress'] ?? 0
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
                        
                        // ВАЖНО: Обновляем статусы всех задач на основе РЕАЛЬНОГО прогресса из event_info.json
                        // Это критично, так как одна Celery задача выполняет все анализы последовательно
                        $this->updateAllTasksFromEventInfo($event);
                        
                        // Перезагружаем задачи после обновления
                        $event->load('celeryTasks');
                        
                        // Проверяем, все ли задачи завершены
                        // КРИТИЧНО: Задача считается завершенной только если:
                        // 1. Статус 'completed' или 'failed'
                        // 2. И прогресс >= 95% (для completed) или прогресс > 0 (для failed)
                        $allCompleted = $event->celeryTasks->every(function ($t) {
                            if (in_array($t->status, ['completed', 'failed'])) {
                                // Для завершенных задач проверяем прогресс
                                if ($t->status === 'completed') {
                                    return $t->progress >= 95;
                                } else {
                                    // Для failed задач прогресс может быть любым
                                    return true;
                                }
                            }
                            return false;
                        });

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
                                    'status' => $t->status
                                ];
                            })->toArray(),
                            'current_task_status' => $status['state'],
                            'current_task_mapped_status' => $mappedStatus
                        ]);

                        if ($allCompleted && in_array($event->status, ['processing', 'draft'])) {
                            $previousStatus = $event->status;
                            
                            // Синхронизируем цены из event_info.json перед публикацией
                            $this->syncPricesFromEventInfo($event);
                            
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
    public function searchSimilarFaces(string $imagePath, ?string $eventId = null, float $threshold = 0.6): array
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
     * Синхронизировать цены фотографий из event_info.json
     * Цена в event_info.json - это цена с комиссией, которая должна быть установлена для всех фотографий
     */
    public function syncPricesFromEventInfo(Event $event): void
    {
        try {
            $eventInfoPath = storage_path('app/public/events/' . $event->id . '/event_info.json');
            
            if (!file_exists($eventInfoPath)) {
                \Log::warning("PhotoProcessingService::syncPricesFromEventInfo: event_info.json not found", [
                    'event_id' => $event->id,
                    'path' => $eventInfoPath
                ]);
                return;
            }
            
            $eventInfo = json_decode(file_get_contents($eventInfoPath), true);
            
            if (!isset($eventInfo['price'])) {
                \Log::warning("PhotoProcessingService::syncPricesFromEventInfo: price not found in event_info.json", [
                    'event_id' => $event->id
                ]);
                return;
            }
            
            $priceFromEventInfo = (float) $eventInfo['price'];
            
            // Обновляем цену события, если она отличается
            $oldEventPrice = $event->price;
            if ($oldEventPrice != $priceFromEventInfo) {
                $event->update(['price' => $priceFromEventInfo]);
                \Log::info("PhotoProcessingService::syncPricesFromEventInfo: Event price updated", [
                    'event_id' => $event->id,
                    'old_price' => $oldEventPrice,
                    'new_price' => $priceFromEventInfo
                ]);
            }
            
            // Обновляем цены всех фотографий события (включая те, у которых цена 0)
            $updatedCount = Photo::where('event_id', $event->id)
                ->where(function($query) use ($priceFromEventInfo) {
                    $query->where('price', '!=', $priceFromEventInfo)
                          ->orWhere('price', 0)
                          ->orWhereNull('price');
                })
                ->update(['price' => $priceFromEventInfo]);
            
            if ($updatedCount > 0) {
                \Log::info("PhotoProcessingService::syncPricesFromEventInfo: Photo prices updated", [
                    'event_id' => $event->id,
                    'price' => $priceFromEventInfo,
                    'updated_count' => $updatedCount
                ]);
            } else {
                \Log::debug("PhotoProcessingService::syncPricesFromEventInfo: No photos to update", [
                    'event_id' => $event->id,
                    'price' => $priceFromEventInfo
                ]);
            }
            
        } catch (\Exception $e) {
            \Log::error("PhotoProcessingService::syncPricesFromEventInfo: Error", [
                'event_id' => $event->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
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
            // ВАЖНО: Убираем timeline, так как он временно отключен
            $sectionMap = [
                'remove_exif' => 'analyze_removeexif',
                'watermark' => 'analyze_watermark',
                'face_search' => 'analyze_facesearch',
                'number_search' => 'analyze_numbersearch',
            ];
            
            $totalPhotos = $eventInfo['photo_count'] ?? count($eventInfo['photo'] ?? []);
            
            // Обновляем каждую задачу на основе реального прогресса
            foreach ($event->celeryTasks as $task) {
                // Пропускаем timeline задачи, так как функционал временно отключен
                if ($task->task_type === 'timeline') {
                    continue;
                }
                
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
                
                // ВАЖНО: Используем максимальное значение между totalPhotos и количеством записей в sectionData
                // Это гарантирует, что прогресс вычисляется правильно даже если event_info.json не обновлен для всех фотографий
                $actualTotal = max($totalPhotos, count($sectionData));
                
                // Вычисляем прогресс
                $progress = $actualTotal > 0 ? intval((($readyCount + $errorCount) / $actualTotal) * 100) : 0;
                
                \Log::debug("PhotoProcessingService::updateAllTasksFromEventInfo: Progress calculation", [
                    'task_type' => $task->task_type,
                    'section_key' => $sectionKey,
                    'ready_count' => $readyCount,
                    'error_count' => $errorCount,
                    'processing_count' => $processingCount,
                    'total_photos' => $totalPhotos,
                    'section_data_count' => count($sectionData),
                    'actual_total' => $actualTotal,
                    'progress' => $progress
                ]);
                
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


