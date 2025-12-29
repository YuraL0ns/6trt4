<?php

namespace App\Http\Client;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Helpers\LogHelper;
use Exception;

class FastApiClient
{
    protected string $baseUrl;
    protected int $timeout;

    public function __construct()
    {
        $this->baseUrl = config('services.fastapi.url', 'http://localhost:8000');
        $this->timeout = config('services.fastapi.timeout', 300);
        
        // Убрано логирование из конструктора, так как оно может вызывать ошибки при инициализации
        // Логирование будет происходить в методах, когда это необходимо
    }

    /**
     * Запустить анализ фотографий события
     */
    public function startEventAnalysis(string $eventId, array $analyses): array
    {
        LogHelper::info("FastApiClient::startEventAnalysis: Starting", [
            'event_id' => $eventId,
            'base_url' => $this->baseUrl,
            'analyses' => $analyses,
            'endpoint' => "{$this->baseUrl}/api/v1/events/{$eventId}/start-analysis"
        ]);

        // Проверяем доступность FastAPI перед вызовом
        if (!$this->healthCheck()) {
            $error = "FastAPI недоступен по адресу: {$this->baseUrl}. Убедитесь, что FastAPI запущен и доступен.";
            LogHelper::error("FastApiClient::startEventAnalysis: FastAPI unavailable", [
                'event_id' => $eventId,
                'base_url' => $this->baseUrl,
                'error' => $error
            ]);
            throw new Exception($error);
        }

        try {
            $response = Http::timeout($this->timeout)
                ->post("{$this->baseUrl}/api/v1/events/{$eventId}/start-analysis", [
                    'analyses' => $analyses
                ]);

            LogHelper::debug("FastApiClient::startEventAnalysis: Response received", [
                'event_id' => $eventId,
                'status' => $response->status(),
                'successful' => $response->successful(),
                'body' => $response->body()
            ]);

            if ($response->successful()) {
                $result = $response->json();
                LogHelper::info("FastApiClient::startEventAnalysis: Success", [
                    'event_id' => $eventId,
                    'result' => $result
                ]);
                return $result;
            }

            $error = "FastAPI error: " . $response->body();
            LogHelper::error("FastApiClient::startEventAnalysis: API error", [
                'event_id' => $eventId,
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            throw new Exception($error);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            $error = "Не удалось подключиться к FastAPI по адресу: {$this->baseUrl}. Проверьте, что FastAPI запущен и доступен в сети Docker.";
            LogHelper::error("FastApiClient::startEventAnalysis: Connection error", [
                'event_id' => $eventId,
                'base_url' => $this->baseUrl,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new Exception($error);
        } catch (Exception $e) {
            LogHelper::error("FastApiClient::startEventAnalysis: Exception", [
                'event_id' => $eventId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Поиск похожих фотографий по лицу
     */
    public function searchByFace(string $imagePath, ?string $eventId = null, float $threshold = 1.2): array
    {
        try {
            Log::info("FastApiClient::searchByFace: Starting", [
                'event_id' => $eventId,
                'threshold' => $threshold,
                'image_path' => $imagePath,
                'base_url' => $this->baseUrl,
                'endpoint' => "{$this->baseUrl}/api/v1/photos/search/face"
            ]);
            
            $response = Http::timeout($this->timeout)
                ->attach('photo', file_get_contents($imagePath), basename($imagePath))
                ->post("{$this->baseUrl}/api/v1/photos/search/face", [
                    'event_id' => $eventId,
                    'threshold' => $threshold
                ]);

            Log::info("FastApiClient::searchByFace: Response received", [
                'event_id' => $eventId,
                'status' => $response->status(),
                'successful' => $response->successful(),
                'body' => $response->body()
                ]);

            if ($response->successful()) {
                $result = $response->json();
                Log::info("FastApiClient::searchByFace: Success", [
                    'event_id' => $eventId,
                    'result' => $result
                ]);
                return $result;
            }

            $error = "FastAPI error: " . $response->body();
            Log::error("FastApiClient::searchByFace: API error", [
                'event_id' => $eventId,
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            throw new Exception($error);
        } catch (Exception $e) {
            Log::error("FastAPI searchByFace error", [
                'event_id' => $eventId,
                'threshold' => $threshold,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
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
            $response = Http::timeout($this->timeout)
                ->attach('photo', file_get_contents($imagePath), basename($imagePath))
                ->post("{$this->baseUrl}/api/v1/photos/search/number", [
                    'event_id' => $eventId
                ]);

            if ($response->successful()) {
                return $response->json();
            }

            throw new Exception("FastAPI error: " . $response->body());
        } catch (Exception $e) {
            Log::error("FastAPI searchByNumber error", [
                'event_id' => $eventId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Получить статус задачи Celery
     */
    public function getTaskStatus(string $taskId): array
    {
        try {
            $response = Http::timeout(10)
                ->get("{$this->baseUrl}/api/v1/tasks/{$taskId}");

            if ($response->successful()) {
                return $response->json();
            }

            throw new Exception("FastAPI error: " . $response->body());
        } catch (Exception $e) {
            Log::error("FastAPI getTaskStatus error", [
                'task_id' => $taskId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Получить event_info.json для события
     */
    public function getEventInfo(string $eventId): ?array
    {
        $maxRetries = 3;
        $retryCount = 0;
        
        while ($retryCount < $maxRetries) {
            try {
                LogHelper::debug("FastApiClient::getEventInfo: Requesting", [
                    'event_id' => $eventId,
                    'base_url' => $this->baseUrl,
                    'endpoint' => "{$this->baseUrl}/api/v1/events/{$eventId}/event-info",
                    'attempt' => $retryCount + 1
                ]);

                $response = Http::timeout(10)
                    ->get("{$this->baseUrl}/api/v1/events/{$eventId}/event-info");

                if ($response->successful()) {
                    try {
                        $result = $response->json();
                        LogHelper::debug("FastApiClient::getEventInfo: Success", [
                            'event_id' => $eventId,
                            'has_data' => !empty($result)
                        ]);
                        return $result;
                    } catch (\Exception $e) {
                        LogHelper::warning("FastApiClient::getEventInfo: JSON decode error", [
                            'event_id' => $eventId,
                            'error' => $e->getMessage(),
                            'body_preview' => substr($response->body(), 0, 200)
                        ]);
                        
                        if ($retryCount < $maxRetries - 1) {
                            $retryCount++;
                            usleep(100000); // 0.1 секунды
                            continue;
                        }
                        
                        return null;
                    }
                }

                if ($response->status() === 404) {
                    LogHelper::debug("FastApiClient::getEventInfo: Not found", [
                        'event_id' => $eventId
                    ]);
                    return null;
                }

                // Если ошибка парсинга JSON на стороне FastAPI
                if ($response->status() === 500) {
                    $errorBody = $response->json();
                    if (isset($errorBody['detail']) && strpos($errorBody['detail'], 'parsing event_info.json') !== false) {
                        LogHelper::error("FastApiClient::getEventInfo: JSON parsing error on FastAPI side", [
                            'event_id' => $eventId,
                            'detail' => $errorBody['detail']
                        ]);
                        
                        if ($retryCount < $maxRetries - 1) {
                            $retryCount++;
                            usleep(200000); // 0.2 секунды перед повтором
                            continue;
                        }
                    }
                }

                LogHelper::warning("FastApiClient::getEventInfo: API error", [
                    'event_id' => $eventId,
                    'status' => $response->status(),
                    'body' => substr($response->body(), 0, 500)
                ]);
                
                if ($retryCount < $maxRetries - 1) {
                    $retryCount++;
                    usleep(100000);
                    continue;
                }
                
                return null;
            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                LogHelper::warning("FastApiClient::getEventInfo: Connection error", [
                    'event_id' => $eventId,
                    'error' => $e->getMessage(),
                    'attempt' => $retryCount + 1
                ]);
                
                if ($retryCount < $maxRetries - 1) {
                    $retryCount++;
                    usleep(200000);
                    continue;
                }
                
                return null;
            } catch (Exception $e) {
                LogHelper::error("FastApiClient::getEventInfo: Exception", [
                    'event_id' => $eventId,
                    'error' => $e->getMessage(),
                    'attempt' => $retryCount + 1
                ]);
                
                if ($retryCount < $maxRetries - 1) {
                    $retryCount++;
                    usleep(100000);
                    continue;
                }
                
                return null;
            }
        }
        
        return null;
    }

    /**
     * Проверка здоровья API
     */
    public function healthCheck(): bool
    {
        try {
            LogHelper::debug("FastApiClient::healthCheck: Checking", [
                'base_url' => $this->baseUrl,
                'health_url' => "{$this->baseUrl}/api/v1/health"
            ]);
            
            $response = Http::timeout(5)
                ->get("{$this->baseUrl}/api/v1/health");

            $success = $response->successful();
            
            LogHelper::debug("FastApiClient::healthCheck: Result", [
                'base_url' => $this->baseUrl,
                'success' => $success,
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            
            return $success;
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            LogHelper::warning("FastAPI health check failed: Connection error", [
                'base_url' => $this->baseUrl,
                'error' => $e->getMessage(),
                'message' => "Не удалось подключиться к FastAPI. Проверьте, что сервис запущен и доступен по адресу: {$this->baseUrl}"
            ]);
            return false;
        } catch (Exception $e) {
            LogHelper::warning("FastAPI health check failed", [
                'base_url' => $this->baseUrl,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }
}


