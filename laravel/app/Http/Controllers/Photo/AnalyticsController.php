<?php

namespace App\Http\Controllers\Photo;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\OrderItem;
use App\Models\Photo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AnalyticsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        // Администратор также имеет доступ к аналитике
        $this->middleware(function ($request, $next) {
            if (!auth()->user()->isAdmin() && !auth()->user()->isPhotographer()) {
                abort(403, 'Доступ запрещен');
            }
            return $next($request);
        });
    }

    /**
     * Аналитика продаж фотографа
     */
    public function index()
    {
        // Администратор видит аналитику всех событий, фотограф - только свои
        $query = Event::query();
        if (Auth::user()->isPhotographer()) {
            $query->where('author_id', Auth::id());
        }
        
        $events = $query->with(['photos.orderItems.order'])
            ->get();

        $analytics = [];
        $totalEarnings = 0;
        $totalPhotosSold = 0;

        foreach ($events as $event) {
            $photosSold = 0;
            $earnings = 0;

            foreach ($event->photos as $photo) {
                $soldCount = $photo->orderItems->count();
                $photosSold += $soldCount;
                $earnings += $photo->orderItems->sum('price');
            }

            $analytics[] = [
                'event' => $event,
                'photos_sold' => $photosSold,
                'earnings' => $earnings,
            ];

            $totalPhotosSold += $photosSold;
            $totalEarnings += $earnings;
        }

        // Получаем все купленные фотографии с количеством продаж
        $purchasedPhotosQuery = Photo::whereHas('orderItems.order', function($query) {
                $query->where('status', 'paid');
            })
            ->with(['event', 'orderItems.order' => function($query) {
                $query->where('status', 'paid');
            }]);

        // Фильтруем по событиям фотографа, если это фотограф
        if (Auth::user()->isPhotographer()) {
            $purchasedPhotosQuery->whereHas('event', function($query) {
                $query->where('author_id', Auth::id());
            });
        }

        $purchasedPhotos = $purchasedPhotosQuery->get()
            ->map(function($photo) {
                $salesCount = $photo->orderItems->where('order.status', 'paid')->count();
                return [
                    'id' => $photo->id,
                    'uuid' => $photo->id,
                    'event' => $photo->event,
                    'event_slug' => $photo->event->slug ?? $photo->event->id,
                    'sales_count' => $salesCount,
                    'total_revenue' => $photo->orderItems->where('order.status', 'paid')->sum('price'),
                ];
            })
            ->filter(function($item) {
                return $item['sales_count'] > 0;
            })
            ->sortByDesc('sales_count')
            ->values();

        return view('photo.analytics', compact('analytics', 'totalEarnings', 'totalPhotosSold', 'purchasedPhotos'));
    }

    /**
     * Скачать архив с купленными фотографиями для события
     */
    public function downloadPurchasedPhotos(Request $request, $eventId)
    {
        // Получаем событие
        $event = Event::findOrFail($eventId);
        
        // Проверяем права доступа
        if (Auth::user()->isPhotographer() && $event->author_id !== Auth::id()) {
            abort(403, 'Доступ запрещен');
        }

        // Получаем все купленные фотографии для этого события
        // Фотография считается купленной, если есть OrderItem с оплаченным заказом
        $purchasedPhotos = Photo::where('event_id', $event->id)
            ->whereHas('orderItems.order', function($query) {
                $query->where('status', 'paid');
            })
            ->with(['orderItems.order' => function($query) {
                $query->where('status', 'paid');
            }])
            ->get()
            ->unique('id'); // Убираем дубликаты, если фото было куплено несколько раз

        if ($purchasedPhotos->isEmpty()) {
            return back()->with('error', 'Для этого события нет купленных фотографий');
        }

        try {
            // Создаем ZIP архив
            $zipFileName = 'purchased_photos_' . $event->slug . '_' . date('Y-m-d') . '.zip';
            $zipPath = storage_path('app/temp/' . $zipFileName);
            $zipDir = dirname($zipPath);
            
            if (!file_exists($zipDir)) {
                mkdir($zipDir, 0755, true);
            }

            $zip = new \ZipArchive();
            $zipOpenResult = $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
            
            if ($zipOpenResult !== TRUE) {
                Log::error("Failed to create ZIP archive for event purchased photos", [
                    'event_id' => $event->id,
                    'error_code' => $zipOpenResult
                ]);
                return back()->with('error', 'Не удалось создать архив');
            }

            $tempFiles = [];
            $addedFilesCount = 0;

            foreach ($purchasedPhotos as $photo) {
                // Определяем имя файла
                $fileName = $photo->original_name ?: 'photo_' . substr($photo->id, 0, 8) . '.jpg';
                
                // Если файл уже добавлен (дубликат), добавляем суффикс
                $counter = 1;
                $originalFileName = $fileName;
                while ($zip->locateName($fileName) !== false) {
                    $pathInfo = pathinfo($originalFileName);
                    $fileName = $pathInfo['filename'] . '_' . $counter . '.' . ($pathInfo['extension'] ?? 'jpg');
                    $counter++;
                }

                $filePath = null;

                // Пытаемся получить оригинал: сначала S3, потом локальный
                if ($photo->s3_original_url) {
                    try {
                        $tempPath = storage_path('app/temp/' . uniqid('s3_') . '_' . $fileName);
                        $tempDir = dirname($tempPath);
                        
                        if (!file_exists($tempDir)) {
                            mkdir($tempDir, 0755, true);
                        }
                        
                        // Скачиваем файл с S3
                        $response = Http::timeout(60)->get($photo->s3_original_url);
                        
                        if ($response->successful()) {
                            file_put_contents($tempPath, $response->body());
                            $filePath = $tempPath;
                            $tempFiles[] = $tempPath;
                        } else {
                            Log::warning("Failed to download photo from S3", [
                                'photo_id' => $photo->id,
                                's3_url' => $photo->s3_original_url,
                                'status' => $response->status()
                            ]);
                        }
                    } catch (\Exception $e) {
                        Log::warning("Error downloading photo from S3", [
                            'photo_id' => $photo->id,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
                
                // Если не удалось скачать с S3, пробуем локальный файл
                if (!$filePath && $photo->original_path) {
                    $localPath = storage_path('app/public/' . $photo->original_path);
                    if (file_exists($localPath)) {
                        $filePath = $localPath;
                    }
                }

                if ($filePath && file_exists($filePath)) {
                    if ($zip->addFile($filePath, $fileName)) {
                        $addedFilesCount++;
                    }
                } else {
                    Log::warning("Photo file not found", [
                        'photo_id' => $photo->id,
                        's3_original_url' => $photo->s3_original_url,
                        'original_path' => $photo->original_path
                    ]);
                }
            }

            $zip->close();

            // Удаляем временные файлы
            foreach ($tempFiles as $tempFile) {
                if (file_exists($tempFile)) {
                    try {
                        unlink($tempFile);
                    } catch (\Exception $e) {
                        Log::warning("Failed to delete temp file", [
                            'temp_file' => $tempFile,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }

            if ($addedFilesCount === 0) {
                if (file_exists($zipPath)) {
                    unlink($zipPath);
                }
                return back()->with('error', 'Не удалось добавить фотографии в архив');
            }

            if (!file_exists($zipPath)) {
                return back()->with('error', 'Архив не был создан');
            }

            Log::info("Generated ZIP archive for event purchased photos", [
                'event_id' => $event->id,
                'photos_count' => $addedFilesCount,
                'zip_path' => $zipPath
            ]);

            // Отдаем файл для скачивания
            return response()->download($zipPath, $zipFileName, [
                'Content-Type' => 'application/zip',
            ])->deleteFileAfterSend(true);

        } catch (\Exception $e) {
            Log::error("Error generating ZIP archive for event purchased photos", [
                'event_id' => $event->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return back()->with('error', 'Ошибка при создании архива: ' . $e->getMessage());
        }
    }
}
