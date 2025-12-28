<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Photo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Helpers\LogHelper;
use Exception;

class PhotoUploadService
{
    protected int $maxFiles = 15000;
    protected int $maxFileSize = 20 * 1024 * 1024; // 20MB

    /**
     * Загрузить фотографии для события
     */
    public function uploadPhotos(Event $event, array $files): array
    {
        // КРИТИЧЕСКОЕ ЛОГИРОВАНИЕ
        \Log::emergency("PhotoUploadService::uploadPhotos: METHOD CALLED", [
            'event_id' => $event->id,
            'files_count' => count($files),
            'max_files' => $this->maxFiles
        ]);
        
        // Также пишем в файл напрямую
        try {
            file_put_contents(
                storage_path('logs/upload_debug.log'),
                date('Y-m-d H:i:s') . " - PhotoUploadService::uploadPhotos CALLED\n" .
                "Event ID: {$event->id}\n" .
                "Files count: " . count($files) . "\n" .
                "---\n",
                FILE_APPEND
            );
        } catch (\Exception $e) {
            \Log::error("Failed to write to upload_debug.log in PhotoUploadService: " . $e->getMessage());
        }
        
        \Log::info("PhotoUploadService: Starting upload", [
            'event_id' => $event->id,
            'files_count' => count($files),
            'max_files' => $this->maxFiles,
            'files_info' => array_map(function($file) {
                return [
                    'name' => $file->getClientOriginalName(),
                    'size' => $file->getSize(),
                    'mime' => $file->getMimeType(),
                    'error' => $file->getError()
                ];
            }, $files)
        ]);
        
        LogHelper::info("PhotoUploadService: Starting upload", [
            'event_id' => $event->id,
            'files_count' => count($files),
            'max_files' => $this->maxFiles
        ]);

        $uploaded = [];
        $errors = [];

        // Проверка количества файлов
        if (count($files) > $this->maxFiles) {
            LogHelper::error("PhotoUploadService: Too many files", [
                'event_id' => $event->id,
                'files_count' => count($files),
                'max_files' => $this->maxFiles
            ]);
            throw new Exception("Превышено максимальное количество файлов: {$this->maxFiles}");
        }

        if (count($files) < 1) {
            LogHelper::error("PhotoUploadService: No files provided", [
                'event_id' => $event->id
            ]);
            throw new Exception("Необходимо загрузить хотя бы один файл");
        }

        $processed = 0;
        foreach ($files as $index => $file) {
            $processed++;
            try {
                \Log::debug("PhotoUploadService: Processing file", [
                    'event_id' => $event->id,
                    'file_index' => $index,
                    'file_name' => $file->getClientOriginalName(),
                    'file_size' => $file->getSize(),
                    'file_mime' => $file->getMimeType(),
                    'file_error' => $file->getError(),
                    'file_is_valid' => $file->isValid(),
                    'processed' => $processed,
                    'total' => count($files)
                ]);
                
                LogHelper::debug("PhotoUploadService: Processing file", [
                    'event_id' => $event->id,
                    'file_index' => $index,
                    'file_name' => $file->getClientOriginalName(),
                    'file_size' => $file->getSize(),
                    'file_mime' => $file->getMimeType(),
                    'processed' => $processed,
                    'total' => count($files)
                ]);

                // Валидация файла
                $this->validateFile($file);

                // Сохраняем оригинальный файл
                $path = $this->storeOriginal($event, $file);
                
                // КРИТИЧЕСКАЯ ПРОВЕРКА: убеждаемся, что файл действительно существует
                $fullPath = storage_path('app/public/' . $path);
                if (!file_exists($fullPath)) {
                    throw new Exception("Файл не был сохранен физически: {$fullPath}. Путь в БД: {$path}");
                }
                
                // Проверяем размер файла
                $fileSize = filesize($fullPath);
                if ($fileSize === false || $fileSize === 0) {
                    throw new Exception("Файл сохранен, но имеет нулевой размер: {$fullPath}");
                }
                
                LogHelper::debug("PhotoUploadService: File stored and verified", [
                    'event_id' => $event->id,
                    'file_name' => $file->getClientOriginalName(),
                    'stored_path' => $path,
                    'full_path' => $fullPath,
                    'file_exists' => file_exists($fullPath),
                    'file_size' => $fileSize
                ]);

                // Создаем запись в БД ТОЛЬКО после успешного сохранения файла
                $photo = Photo::create([
                    'event_id' => $event->id,
                    'original_path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'price' => $event->price ?? 0,
                    'status' => 'pending',
                ]);

                LogHelper::debug("PhotoUploadService: Photo created in DB", [
                    'event_id' => $event->id,
                    'photo_id' => $photo->id,
                    'file_name' => $file->getClientOriginalName()
                ]);

                $uploaded[] = $photo;
            } catch (Exception $e) {
                $errors[] = [
                    'file' => $file->getClientOriginalName(),
                    'error' => $e->getMessage()
                ];
                LogHelper::error("PhotoUploadService: Upload error", [
                    'event_id' => $event->id,
                    'file' => $file->getClientOriginalName(),
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }

        LogHelper::info("PhotoUploadService: Upload completed", [
            'event_id' => $event->id,
            'uploaded' => count($uploaded),
            'errors' => count($errors),
            'total_files' => count($files)
        ]);

        return [
            'uploaded' => count($uploaded),
            'errors' => count($errors),
            'photos' => $uploaded,
            'error_details' => $errors
        ];
    }

    /**
     * Валидация файла
     */
    protected function validateFile(UploadedFile $file): void
    {
        // Проверка ошибок загрузки
        if ($file->getError() !== UPLOAD_ERR_OK) {
            $errorMessages = [
                UPLOAD_ERR_INI_SIZE => 'Файл превышает upload_max_filesize',
                UPLOAD_ERR_FORM_SIZE => 'Файл превышает MAX_FILE_SIZE',
                UPLOAD_ERR_PARTIAL => 'Файл загружен частично',
                UPLOAD_ERR_NO_FILE => 'Файл не был загружен',
                UPLOAD_ERR_NO_TMP_DIR => 'Отсутствует временная папка',
                UPLOAD_ERR_CANT_WRITE => 'Не удалось записать файл на диск',
                UPLOAD_ERR_EXTENSION => 'Загрузка остановлена расширением',
            ];
            $errorMsg = $errorMessages[$file->getError()] ?? 'Неизвестная ошибка загрузки';
            throw new Exception("Ошибка загрузки файла: {$errorMsg} (код: {$file->getError()})");
        }

        // Проверка типа файла
        if (!$file->isValid()) {
            throw new Exception("Файл невалиден: " . $file->getError());
        }

        // Проверка размера
        if ($file->getSize() > $this->maxFileSize) {
            throw new Exception("Размер файла ({$file->getSize()} байт) превышает 20MB");
        }

        // Проверка MIME типа
        $allowedMimes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
        $mimeType = $file->getMimeType();
        if (!in_array($mimeType, $allowedMimes)) {
            throw new Exception("Недопустимый тип файла: {$mimeType}. Разрешены: " . implode(', ', $allowedMimes));
        }
    }

    /**
     * Сохранить оригинальный файл
     */
    protected function storeOriginal(Event $event, UploadedFile $file): string
    {
        // КРИТИЧЕСКОЕ ЛОГИРОВАНИЕ
        \Log::emergency("PhotoUploadService::storeOriginal: METHOD CALLED", [
            'event_id' => $event->id,
            'file_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize()
        ]);
        
        // Также пишем в файл напрямую
        try {
            file_put_contents(
                storage_path('logs/upload_debug.log'),
                date('Y-m-d H:i:s') . " - PhotoUploadService::storeOriginal CALLED\n" .
                "Event ID: {$event->id}\n" .
                "File: " . $file->getClientOriginalName() . "\n" .
                "Size: " . $file->getSize() . "\n" .
                "---\n",
                FILE_APPEND
            );
        } catch (\Exception $e) {
            \Log::error("Failed to write to upload_debug.log in storeOriginal: " . $e->getMessage());
        }
        
        // Создаем папку для загрузки
        $directory = "events/{$event->id}/upload";
        
        // Создаем директорию если её нет
        $fullDirectory = storage_path('app/public/' . $directory);
        
        \Log::debug("PhotoUploadService::storeOriginal: Preparing directory", [
            'event_id' => $event->id,
            'directory' => $directory,
            'full_directory' => $fullDirectory,
            'directory_exists' => is_dir($fullDirectory),
            'is_writable' => is_dir($fullDirectory) ? is_writable($fullDirectory) : false
        ]);
        
        LogHelper::debug("PhotoUploadService::storeOriginal: Preparing directory", [
            'event_id' => $event->id,
            'directory' => $directory,
            'full_directory' => $fullDirectory,
            'exists' => file_exists($fullDirectory),
            'is_writable' => file_exists($fullDirectory) ? is_writable($fullDirectory) : false
        ]);
        
        // Используем Storage facade для создания директории (согласно документации Laravel)
        // Storage::makeDirectory автоматически создает все необходимые поддиректории
        // Проверяем через file_exists, так как Storage::exists может работать некорректно
        if (!file_exists($fullDirectory) || !is_dir($fullDirectory)) {
            try {
                // Сначала пробуем создать через mkdir (более надежно для физической файловой системы)
                if (!file_exists($fullDirectory)) {
                    $created = @mkdir($fullDirectory, 0755, true);
                    if (!$created && !is_dir($fullDirectory)) {
                        throw new Exception("Не удалось создать директорию через mkdir: {$fullDirectory}");
                    }
                    \Log::info("PhotoUploadService::storeOriginal: Directory created via mkdir", [
                        'event_id' => $event->id,
                        'directory' => $directory,
                        'full_directory' => $fullDirectory,
                        'exists' => file_exists($fullDirectory),
                        'is_dir' => is_dir($fullDirectory)
                    ]);
                }
                
                // Также создаем через Storage facade для консистентности
                if (!Storage::disk('public')->exists($directory)) {
                    Storage::disk('public')->makeDirectory($directory);
                    \Log::info("PhotoUploadService::storeOriginal: Directory created via Storage::makeDirectory", [
                        'event_id' => $event->id,
                        'directory' => $directory,
                        'exists' => Storage::disk('public')->exists($directory)
                    ]);
                }
                
                LogHelper::info("PhotoUploadService::storeOriginal: Directory created", [
                    'event_id' => $event->id,
                    'directory' => $directory,
                    'full_directory' => $fullDirectory
                ]);
            } catch (\Exception $e) {
                \Log::error("PhotoUploadService::storeOriginal: Failed to create directory", [
                    'event_id' => $event->id,
                    'directory' => $directory,
                    'full_directory' => $fullDirectory,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                
                // Последняя попытка через mkdir
                if (!file_exists($fullDirectory)) {
                    $created = @mkdir($fullDirectory, 0755, true);
                    if (!$created && !is_dir($fullDirectory)) {
                        // Логируем в файл перед выбросом исключения
                        try {
                            file_put_contents(
                                storage_path('logs/upload_debug.log'),
                                date('Y-m-d H:i:s') . " - FAILED TO CREATE DIRECTORY\n" .
                                "Directory: {$fullDirectory}\n" .
                                "Error: {$e->getMessage()}\n" .
                                "---\n",
                                FILE_APPEND
                            );
                        } catch (\Exception $logError) {
                            // Игнорируем ошибку логирования
                        }
                        throw new Exception("Не удалось создать директорию после всех попыток: {$fullDirectory}. Ошибка: {$e->getMessage()}");
                    }
                }
            }
        }
        
        // Логируем успешное создание директории
        try {
            file_put_contents(
                storage_path('logs/upload_debug.log'),
                date('Y-m-d H:i:s') . " - Directory check\n" .
                "Directory: {$fullDirectory}\n" .
                "Exists: " . (file_exists($fullDirectory) ? 'YES' : 'NO') . "\n" .
                "Is dir: " . (is_dir($fullDirectory) ? 'YES' : 'NO') . "\n" .
                "Writable: " . (is_writable($fullDirectory) ? 'YES' : 'NO') . "\n" .
                "---\n",
                FILE_APPEND
            );
        } catch (\Exception $e) {
            // Игнорируем ошибку логирования
        }
        
        // КРИТИЧЕСКАЯ ПРОВЕРКА: убеждаемся, что директория действительно существует
        if (!file_exists($fullDirectory) || !is_dir($fullDirectory)) {
            \Log::error("PhotoUploadService::storeOriginal: Directory does not exist after creation", [
                'event_id' => $event->id,
                'directory' => $directory,
                'full_directory' => $fullDirectory,
                'parent_exists' => file_exists(dirname($fullDirectory)),
                'parent_writable' => is_writable(dirname($fullDirectory))
            ]);
            throw new Exception("Директория не существует после попытки создания: {$fullDirectory}");
        }
        
        // Проверяем права доступа
        if (!is_writable($fullDirectory)) {
            \Log::warning("PhotoUploadService::storeOriginal: Directory is not writable", [
                'event_id' => $event->id,
                'directory' => $directory,
                'full_directory' => $fullDirectory,
                'permissions' => substr(sprintf('%o', fileperms($fullDirectory)), -4)
            ]);
            // Пробуем установить права
            @chmod($fullDirectory, 0755);
            if (!is_writable($fullDirectory)) {
                throw new Exception("Директория не доступна для записи: {$fullDirectory}");
            }
        }
        
        // Сохраняем оригинальное имя файла, но добавляем уникальный префикс для избежания конфликтов
        $originalName = $file->getClientOriginalName();
        $filename = uniqid() . '_' . time() . '_' . $originalName;
        
        // Очищаем имя файла от недопустимых символов
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);

        LogHelper::debug("PhotoUploadService::storeOriginal: Storing file", [
            'event_id' => $event->id,
            'original_name' => $originalName,
            'filename' => $filename,
            'directory' => $directory,
            'file_size' => $file->getSize()
        ]);

        // Сохраняем файл
        \Log::debug("PhotoUploadService::storeOriginal: Attempting to store file", [
            'event_id' => $event->id,
            'original_name' => $originalName,
            'filename' => $filename,
            'directory' => $directory,
            'file_size' => $file->getSize(),
            'file_error' => $file->getError(),
            'file_is_valid' => $file->isValid()
        ]);
        
        // Пробуем сохранить через storeAs
        $path = null;
        try {
            $path = $file->storeAs($directory, $filename, 'public');
            
            \Log::debug("PhotoUploadService::storeOriginal: File stored via storeAs", [
                'event_id' => $event->id,
                'path' => $path,
                'path_is_null' => is_null($path),
                'path_is_empty' => empty($path)
            ]);
            
            // Проверяем, что файл действительно сохранен
            if ($path) {
                $testPath = storage_path('app/public/' . $path);
                if (!file_exists($testPath)) {
                    \Log::warning("PhotoUploadService::storeOriginal: storeAs returned path but file doesn't exist", [
                        'event_id' => $event->id,
                        'path' => $path,
                        'test_path' => $testPath
                    ]);
                    $path = null; // Сбрасываем путь, чтобы попробовать другой метод
                }
            }
        } catch (\Exception $e) {
            \Log::error("PhotoUploadService::storeOriginal: storeAs threw exception", [
                'event_id' => $event->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $path = null;
        }
        
        // Если storeAs не сработал, пробуем через Storage напрямую
        if (!$path) {
            \Log::warning("PhotoUploadService::storeOriginal: storeAs failed, trying Storage::disk", [
                'event_id' => $event->id,
                'directory' => $directory,
                'filename' => $filename
            ]);
            
            try {
                $path = Storage::disk('public')->putFileAs($directory, $file, $filename);
                
                // Проверяем, что файл действительно сохранен
                if ($path) {
                    $testPath = storage_path('app/public/' . $path);
                    if (!file_exists($testPath)) {
                        \Log::warning("PhotoUploadService::storeOriginal: Storage::disk returned path but file doesn't exist", [
                            'event_id' => $event->id,
                            'path' => $path,
                            'test_path' => $testPath
                        ]);
                        $path = null;
                    } else {
                        \Log::info("PhotoUploadService::storeOriginal: File stored via Storage::disk", [
                            'event_id' => $event->id,
                            'path' => $path,
                            'file_exists' => true,
                            'file_size' => filesize($testPath)
                        ]);
                    }
                }
            } catch (\Exception $e) {
                \Log::error("PhotoUploadService::storeOriginal: Storage::disk also failed", [
                    'event_id' => $event->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                $path = null;
            }
        }
        
        // Если и это не сработало, пробуем через move_uploaded_file
        if (!$path) {
            \Log::warning("PhotoUploadService::storeOriginal: Storage::disk failed, trying move_uploaded_file", [
                'event_id' => $event->id,
                'directory' => $directory,
                'filename' => $filename
            ]);
            
            $fullPath = $fullDirectory . '/' . $filename;
            $moved = move_uploaded_file($file->getRealPath(), $fullPath);
            
            if ($moved) {
                // Проверяем, что файл действительно перемещен
                if (file_exists($fullPath)) {
                    $path = $directory . '/' . $filename;
                    \Log::info("PhotoUploadService::storeOriginal: File moved via move_uploaded_file", [
                        'event_id' => $event->id,
                        'path' => $path,
                        'full_path' => $fullPath,
                        'file_size' => filesize($fullPath)
                    ]);
                } else {
                    \Log::error("PhotoUploadService::storeOriginal: move_uploaded_file returned true but file doesn't exist", [
                        'event_id' => $event->id,
                        'full_path' => $fullPath,
                        'source' => $file->getRealPath()
                    ]);
                    $path = null;
                }
            } else {
                \Log::error("PhotoUploadService::storeOriginal: move_uploaded_file failed", [
                    'event_id' => $event->id,
                    'source' => $file->getRealPath(),
                    'destination' => $fullPath,
                    'file_exists' => file_exists($file->getRealPath()),
                    'dir_writable' => is_writable($fullDirectory),
                    'dir_exists' => is_dir($fullDirectory)
                ]);
                $path = null;
            }
        }
        
        if (!$path) {
            \Log::error("PhotoUploadService::storeOriginal: All storage methods failed", [
                'event_id' => $event->id,
                'original_name' => $originalName,
                'filename' => $filename,
                'directory' => $directory
            ]);
            throw new Exception("Не удалось сохранить файл: {$originalName}");
        }
        
        // Устанавливаем права доступа к файлу
        $fullPath = storage_path('app/public/' . $path);
        
        \Log::debug("PhotoUploadService::storeOriginal: Checking file existence", [
            'event_id' => $event->id,
            'path' => $path,
            'full_path' => $fullPath,
            'file_exists' => file_exists($fullPath),
            'is_readable' => file_exists($fullPath) ? is_readable($fullPath) : false,
            'is_writable' => file_exists($fullPath) ? is_writable($fullPath) : false
        ]);
        
        if (!file_exists($fullPath)) {
            \Log::error("PhotoUploadService::storeOriginal: File not found after storeAs", [
                'event_id' => $event->id,
                'path' => $path,
                'full_path' => $fullPath,
                'directory_exists' => is_dir($fullDirectory),
                'directory_listing' => is_dir($fullDirectory) ? scandir($fullDirectory) : []
            ]);
            throw new Exception("Файл не был сохранен: {$fullPath}");
        }
        
        chmod($fullPath, 0644);
        
        \Log::info("PhotoUploadService::storeOriginal: File stored successfully", [
            'event_id' => $event->id,
            'path' => $path,
            'full_path' => $fullPath,
            'file_size' => filesize($fullPath)
        ]);
        
        LogHelper::debug("PhotoUploadService::storeOriginal: File stored", [
            'event_id' => $event->id,
            'path' => $path,
            'full_path' => $fullPath,
            'file_exists' => file_exists($fullPath),
            'file_size' => filesize($fullPath),
            'is_readable' => is_readable($fullPath)
        ]);
        
        return $path;
    }

    /**
     * Удалить фотографию
     */
    public function deletePhoto(Photo $photo): bool
    {
        try {
            // Удаляем файлы
            if ($photo->original_path && Storage::disk('public')->exists($photo->original_path)) {
                Storage::disk('public')->delete($photo->original_path);
            }

            if ($photo->custom_path && Storage::disk('public')->exists($photo->custom_path)) {
                Storage::disk('public')->delete($photo->custom_path);
            }

            // Удаляем запись из БД
            $photo->delete();

            return true;
        } catch (Exception $e) {
            Log::error("Delete photo error", [
                'photo_id' => $photo->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}


