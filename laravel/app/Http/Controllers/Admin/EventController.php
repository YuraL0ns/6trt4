<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\Request;

class EventController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('group:admin');
    }

    /**
     * Список всех событий
     */
    public function index()
    {
        try {
            // Загружаем события с авторами
            $events = Event::with('author')
                ->orderBy('created_at', 'desc')
                ->paginate(20);

            return view('admin.events.index', compact('events'));
        } catch (\Exception $e) {
            // Безопасное логирование - оборачиваем в try-catch на случай проблем с конфигурацией логирования
            try {
                \Log::error("Admin/EventController::index error", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]);
            } catch (\Exception $logException) {
                // Если логирование не работает, просто продолжаем
            }
            
            // Возвращаем более информативную ошибку для разработки
            if (config('app.debug')) {
                return response()->json([
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ], 500);
            }
            
            abort(500, 'Ошибка при загрузке событий. Проверьте логи для деталей.');
        }
    }

    /**
     * Редактировать событие
     */
    public function edit(string $id)
    {
        $event = Event::with('author')->findOrFail($id);
        return view('admin.events.edit', compact('event'));
    }

    /**
     * Обновить событие
     */
    public function update(Request $request, string $id)
    {
        $event = Event::findOrFail($id);

        $request->validate([
            'title' => 'required|string|max:255',
            'city' => 'required|string|max:255',
            'date' => 'required|date',
            'price' => 'required|numeric|min:0',
            'status' => 'required|in:draft,processing,published,completed,archived',
            'description' => 'nullable|string|max:5000',
        ]);

        // Получаем старую цену для сравнения
        $oldPrice = (float) $event->price;
        
        // Преобразуем цену в правильный формат (decimal с 2 знаками после запятой)
        // Используем number_format для правильного форматирования, затем преобразуем обратно в float
        $price = (float) number_format((float) $request->price, 2, '.', '');
        
        // Обновляем событие
        $event->update([
            'title' => $request->title,
            'city' => $request->city,
            'date' => $request->date,
            'price' => $price,
            'status' => $request->status,
            'description' => $request->description,
        ]);

        // Если цена изменилась, синхронизируем цены всех фотографий события
        // Используем небольшой допуск для сравнения float значений
        if (abs($oldPrice - $price) > 0.001) {
            $photosUpdated = \App\Models\Photo::where('event_id', $event->id)
                ->update(['price' => $price]);
            
            \Log::info("Event price updated, photos prices synced", [
                'event_id' => $event->id,
                'old_price' => $oldPrice,
                'new_price' => $price,
                'photos_updated' => $photosUpdated
            ]);
        }

        return back()->with('success', 'Событие обновлено');
    }

    /**
     * Архивировать событие
     */
    public function archive(string $id)
    {
        $event = Event::findOrFail($id);
        
        $event->update(['status' => 'archived']);
        
        // Запускаем задачу архивирования в FastAPI
        try {
            $fastApiUrl = config('services.fastapi.url');
            $response = \Illuminate\Support\Facades\Http::timeout(30)->post(
                "{$fastApiUrl}/events/{$event->id}/archive"
            );
            
            if ($response->successful()) {
                \Log::info("Archive task started for event", [
                    'event_id' => $event->id,
                    'task_id' => $response->json('task_id')
                ]);
            } else {
                \Log::warning("Failed to start archive task", [
                    'event_id' => $event->id,
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
            }
        } catch (\Exception $e) {
            \Log::error("Error starting archive task", [
                'event_id' => $event->id,
                'error' => $e->getMessage()
            ]);
            // Не прерываем процесс, событие уже архивировано
        }
        
        return back()->with('success', 'Событие архивировано и скрыто со страниц сайта');
    }

    /**
     * Разархивировать событие
     */
    public function unarchive(string $id)
    {
        $event = Event::findOrFail($id);
        
        // Возвращаем статус на published, если событие было опубликовано
        if ($event->status === 'archived') {
            $event->update(['status' => 'published']);
        }
        
        return back()->with('success', 'Событие разархивировано');
    }

    /**
     * Удалить событие полностью
     */
    public function destroy(string $id)
    {
        $event = Event::findOrFail($id);
        
        try {
            // Удаляем все фотографии события
            foreach ($event->photos as $photo) {
                try {
                    // Удаляем файлы с диска
                    if ($photo->original_path && \Storage::disk('public')->exists($photo->original_path)) {
                        \Storage::disk('public')->delete($photo->original_path);
                    }
                    
                    if ($photo->custom_path && \Storage::disk('public')->exists($photo->custom_path)) {
                        \Storage::disk('public')->delete($photo->custom_path);
                    }
                } catch (\Exception $e) {
                    \Log::warning("Error deleting photo files", [
                        'photo_id' => $photo->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            
            // Удаляем папку события со всеми файлами
            $eventDir = storage_path('app/public/events/' . $event->id);
            if (is_dir($eventDir)) {
                try {
                    \File::deleteDirectory($eventDir);
                } catch (\Exception $e) {
                    \Log::warning("Error deleting event directory", [
                        'event_id' => $event->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            
            // Удаляем задачи Celery
            $event->celeryTasks()->delete();
            
            // Удаляем событие (каскадно удалятся все связанные записи)
            $eventTitle = $event->title;
            $event->delete();
            
            \Log::info("Event deleted", [
                'event_id' => $id,
                'event_title' => $eventTitle,
                'admin_id' => auth()->id()
            ]);
            
            return redirect()->route('admin.events.index')->with('success', 'Событие "' . $eventTitle . '" и все связанные данные успешно удалены.');
            
        } catch (\Exception $e) {
            \Log::error("Error deleting event", [
                'event_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return back()->with('error', 'Ошибка при удалении события: ' . $e->getMessage());
        }
    }

    /**
     * Удалить все фотографии пользователя, но сохранить event_info.json
     */
    public function deleteUserPhotos(string $id)
    {
        $event = Event::findOrFail($id);
        
        try {
            $eventInfoPath = storage_path('app/public/events/' . $event->id . '/event_info.json');
            
            // Проверяем существование event_info.json
            if (!file_exists($eventInfoPath)) {
                return back()->with('error', 'Файл event_info.json не найден. Удаление отменено.');
            }
            
            // Удаляем все фотографии события
            $photos = $event->photos;
            $deletedCount = 0;
            
            foreach ($photos as $photo) {
                try {
                    // Удаляем файлы с диска
                    if ($photo->original_path && \Storage::disk('public')->exists($photo->original_path)) {
                        \Storage::disk('public')->delete($photo->original_path);
                    }
                    
                    if ($photo->custom_path && \Storage::disk('public')->exists($photo->custom_path)) {
                        \Storage::disk('public')->delete($photo->custom_path);
                    }
                    
                    // Удаляем из S3 если есть URL
                    if ($photo->s3_custom_url || $photo->s3_original_url) {
                        // TODO: Добавить удаление из S3 если нужно
                        \Log::info("Photo has S3 URLs, skipping S3 deletion", [
                            'photo_id' => $photo->id,
                            's3_custom_url' => $photo->s3_custom_url,
                            's3_original_url' => $photo->s3_original_url
                        ]);
                    }
                    
                    // Удаляем запись из БД
                    $photo->delete();
                    $deletedCount++;
                } catch (\Exception $e) {
                    \Log::warning("Error deleting photo", [
                        'photo_id' => $photo->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            
            // Удаляем папки с фотографиями, но сохраняем event_info.json
            $eventDir = storage_path('app/public/events/' . $event->id);
            $dirsToDelete = ['upload', 'original_photo', 'custom_photo'];
            
            foreach ($dirsToDelete as $dir) {
                $dirPath = $eventDir . '/' . $dir;
                if (is_dir($dirPath)) {
                    try {
                        \File::deleteDirectory($dirPath);
                    } catch (\Exception $e) {
                        \Log::warning("Error deleting directory", [
                            'dir' => $dirPath,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }
            
            // Проверяем, что event_info.json все еще существует
            if (!file_exists($eventInfoPath)) {
                \Log::error("event_info.json was deleted during photo deletion", [
                    'event_id' => $event->id
                ]);
                return back()->with('error', 'Ошибка: event_info.json был удален. Проверьте логи.');
            }
            
            \Log::info("User photos deleted, event_info.json preserved", [
                'event_id' => $event->id,
                'deleted_photos' => $deletedCount,
                'admin_id' => auth()->id()
            ]);
            
            return back()->with('success', "Удалено фотографий: {$deletedCount}. Файл event_info.json сохранен.");
            
        } catch (\Exception $e) {
            \Log::error("Error deleting user photos", [
                'event_id' => $event->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return back()->with('error', 'Ошибка при удалении фотографий: ' . $e->getMessage());
        }
    }
}
