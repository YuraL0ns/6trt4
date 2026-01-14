@extends('layouts.app')

@section('title', $event->title . ' - Hunter-Photo.Ru')
@section('page-title', $event->title)

@php
    use Illuminate\Support\Facades\Storage;
@endphp

@section('content')
    <!-- Сообщения об успехе/ошибке -->
    @if(session('success'))
        <x-alert type="success" class="mb-6">
            {{ session('success') }}
        </x-alert>
    @endif
    
    @if(session('error'))
        <x-alert type="error" class="mb-6">
            {{ session('error') }}
        </x-alert>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        <!-- Левая колонка - Информация о событии -->
        <div class="lg:col-span-2">
            <x-card>
                <h2 class="text-2xl font-bold text-white mb-4">{{ $event->title }}</h2>
                <div class="space-y-2 mb-4">
                    <div class="flex items-center space-x-2">
                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                        <p class="text-gray-300">{{ $event->city }}</p>
                    </div>
                    <div class="flex items-center space-x-2">
                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                        <p class="text-gray-300">{{ $event->getFormattedDate() }}</p>
                    </div>
                    <div class="flex items-center space-x-2">
                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <p class="text-gray-300">Цена за фото: {{ number_format($event->price, 0, ',', ' ') }} ₽</p>
                    </div>
                    <div class="flex items-center space-x-2">
                        <p class="text-gray-300">Slug: <code class="text-sm bg-gray-800 px-2 py-1 rounded">{{ $event->slug }}</code></p>
                    </div>
                </div>
                
                <x-badge variant="{{ $event->status === 'published' ? 'success' : ($event->status === 'processing' ? 'warning' : 'default') }}">
                    @if($event->status === 'draft') Черновик
                    @elseif($event->status === 'processing') В обработке
                    @elseif($event->status === 'published') Опубликовано
                    @else Завершено
                    @endif
                </x-badge>
                
                @if($event->description)
                    <div class="mt-4 pt-4 border-t border-gray-700">
                        <p class="text-gray-300">{{ $event->description }}</p>
                    </div>
                @endif
            </x-card>
        </div>

        <!-- Правая колонка - Действия -->
        <div class="lg:col-span-1">
            @if($event->status === 'draft' || (auth()->user()->isAdmin() && $event->status !== 'published'))
                <x-card>
                    <h3 class="text-lg font-semibold text-white mb-4">Действия</h3>
                    <div class="space-y-4">
                        <x-button onclick="openUploadModal()" class="w-full" size="lg">
                            Загрузить фотографии
                        </x-button>
                        @if($event->photos->count() > 0)
                            <x-button onclick="openAnalysisModal()" variant="outline" class="w-full" size="lg">
                                Запустить анализ
                            </x-button>
                        @else
                            <x-button disabled variant="outline" class="w-full" size="lg">
                                Загрузите фото для анализа
                            </x-button>
                        @endif
                    </div>
                </x-card>
            @endif
        </div>
    </div>

    <!-- Обложка и статус анализа (рядом) -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 mb-6">
        <!-- Обложка события (6 колонок) -->
        <div class="lg:col-span-6">
            <x-card>
                <h3 class="text-lg font-semibold text-white mb-4">Обложка события</h3>
                @if($event->cover_path)
                    <div class="aspect-video bg-gray-800 rounded-lg overflow-hidden">
                        @php
                            $coverPath = $event->cover_path;
                            $fullCoverPath = storage_path('app/public/' . $coverPath);
                            $coverExists = file_exists($fullCoverPath);
                            
                            // Пробуем разные способы получения URL
                            $coverUrl = null;
                            if ($coverExists) {
                                // Способ 1: через Storage::url
                                $coverUrl = Storage::url($coverPath);
                                // Если путь не начинается с /storage, пробуем другой способ
                                if (!str_starts_with($coverUrl, '/storage')) {
                                    $coverUrl = '/storage/' . ltrim($coverPath, '/');
                                }
                            }
                        @endphp
                        @if($coverUrl && $coverExists)
                            <img src="{{ $coverUrl }}" alt="{{ $event->title }}" class="w-full h-full object-cover" onerror="this.parentElement.innerHTML='<div class=\'w-full h-full flex items-center justify-center text-gray-400\'>Обложка не найдена ({{ $event->cover_path }})</div>'">
                        @else
                            <div class="w-full h-full flex flex-col items-center justify-center text-gray-400 bg-gray-800">
                                <p>Обложка не найдена</p>
                                <small class="text-xs text-gray-500 mt-2">Путь: {{ $coverPath }}</small>
                                <small class="text-xs text-gray-500">Полный путь: {{ $fullCoverPath }}</small>
                                <small class="text-xs text-gray-500">Существует: {{ $coverExists ? 'Да' : 'Нет' }}</small>
                                <small class="text-xs text-gray-500">URL: {{ $coverUrl ?? 'null' }}</small>
                            </div>
                        @endif
                    </div>
                @else
                    <!-- Форма загрузки обложки для отложенного события -->
                    <div class="border-2 border-dashed border-gray-700 rounded-lg p-6">
                        <form action="{{ route('photo.events.upload-cover', $event->slug) }}" method="POST" enctype="multipart/form-data" id="cover-upload-form">
                            @csrf
                            <div class="text-center">
                                <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                    <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                                <p class="mt-2 text-sm text-gray-300">Обложка не загружена</p>
                                <p class="mt-1 text-xs text-gray-500">Загрузите обложку для события</p>
                                <div class="mt-4">
                                    <input 
                                        type="file" 
                                        name="cover" 
                                        id="cover-input"
                                        accept="image/jpeg,image/jpg,image/png" 
                                        required
                                        class="hidden"
                                        onchange="document.getElementById('cover-filename').textContent = this.files[0] ? 'Выбран: ' + this.files[0].name : ''"
                                    >
                                    <label for="cover-input" class="cursor-pointer inline-flex items-center px-4 py-2 bg-[#a78bfa] hover:bg-[#8b5cf6] text-white rounded-lg transition-colors">
                                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
                                        </svg>
                                        Выбрать файл
                                    </label>
                                    <p id="cover-filename" class="mt-2 text-sm text-gray-400"></p>
                                </div>
                                <div class="mt-4">
                                    <button type="submit" class="px-4 py-2 bg-[#a78bfa] hover:bg-[#8b5cf6] text-white rounded-lg transition-colors">
                                        Загрузить обложку
                                    </button>
                                </div>
                                <p class="mt-2 text-xs text-gray-500">Максимальный размер: 10 МБ. Форматы: JPEG, PNG</p>
                            </div>
                        </form>
                    </div>
                @endif
            </x-card>
        </div>

        <!-- Статус анализа (6 колонок, или 12 если обложки нет) -->
        <div class="{{ $event->cover_path ? 'lg:col-span-6' : 'lg:col-span-12' }}" id="analysis-status-card-wrapper">
            <x-card title="Статус анализа" id="analysis-status-card">
                <div id="analysis-status-content" class="space-y-4">
                    @php
                        // Определяем какие анализы должны быть показаны
                        $expectedTasks = [];
                        $taskNames = [
                            'timeline' => 'Timeline',
                            'remove_exif' => 'Удаление EXIF',
                            'watermark' => 'Водяной знак',
                            'face_search' => 'Поиск лиц',
                            'number_search' => 'Поиск номеров',
                        ];
                        
                        if (isset($analyses) && !empty($analyses)) {
                            // Если event_info.json существует, показываем только включенные анализы
                            // Нормализуем значения - конвертируем строки "1"/"0" в boolean
                            $normalizedAnalyses = [];
                            foreach ($analyses as $key => $value) {
                                if ($value === "1" || $value === 1 || $value === true) {
                                    $normalizedAnalyses[$key] = true;
                                } elseif ($value === "0" || $value === 0 || $value === false) {
                                    $normalizedAnalyses[$key] = false;
                                } else {
                                    $normalizedAnalyses[$key] = (bool)$value;
                                }
                            }
                            
                            if ($normalizedAnalyses['timeline'] ?? false) {
                                $expectedTasks['timeline'] = $taskNames['timeline'];
                            }
                            if ($normalizedAnalyses['remove_exif'] ?? true) {
                                $expectedTasks['remove_exif'] = $taskNames['remove_exif'];
                            }
                            if ($normalizedAnalyses['watermark'] ?? true) {
                                $expectedTasks['watermark'] = $taskNames['watermark'];
                            }
                            if ($normalizedAnalyses['face_search'] ?? false) {
                                $expectedTasks['face_search'] = $taskNames['face_search'];
                            }
                            if ($normalizedAnalyses['number_search'] ?? false) {
                                $expectedTasks['number_search'] = $taskNames['number_search'];
                            }
                        } else {
                            // Если event_info.json не найден, показываем все задачи из БД
                            foreach ($event->celeryTasks as $task) {
                                if (isset($taskNames[$task->task_type])) {
                                    $expectedTasks[$task->task_type] = $taskNames[$task->task_type];
                                }
                            }
                        }
                        
                        // Создаем массив задач с их статусами
                        $tasksMap = [];
                        foreach ($event->celeryTasks as $task) {
                            $tasksMap[$task->task_type] = $task;
                        }
                        
                        // Показываем все ожидаемые задачи
                        $totalPhotos = $event->photos->count();
                    @endphp
                    
                    @if(count($expectedTasks) > 0)
                        @foreach($expectedTasks as $taskType => $taskName)
                            @php
                                $task = $tasksMap[$taskType] ?? null;
                                $status = $task ? $task->status : 'pending';
                                $progress = $task ? $task->progress : 0;
                                $processed = $task ? round(($progress / 100) * $totalPhotos) : 0;
                            @endphp
                            <div class="analysis-task-item" data-task-type="{{ $taskType }}">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-sm font-medium text-white">{{ $taskName }}</span>
                                    <x-badge variant="{{ $status === 'completed' ? 'success' : ($status === 'processing' ? 'warning' : 'default') }}" id="task-status-{{ $taskType }}">
                                        <span id="task-status-text-{{ $taskType }}">
                                            {{ $status === 'completed' ? 'Завершено' : ($status === 'processing' ? 'В процессе' : 'Ожидание') }}
                                        </span>
                                    </x-badge>
                                </div>
                                <div class="w-full bg-gray-700 rounded-full h-2">
                                    <div class="bg-[#a78bfa] h-2 rounded-full transition-all" id="task-progress-bar-{{ $taskType }}" style="width: {{ $progress }}%"></div>
                                </div>
                                <p class="text-xs text-gray-400 mt-1">
                                    Прогресс: <span id="task-progress-text-{{ $taskType }}">{{ $progress }}</span>% 
                                    (<span id="task-processed-{{ $taskType }}">{{ $processed }}</span> / <span id="task-total-{{ $taskType }}">{{ $totalPhotos }}</span> фото)
                                </p>
                            </div>
                        @endforeach
                    @else
                        <p class="text-sm text-gray-400">Анализ еще не запущен. Загрузите фотографии и запустите анализ.</p>
                    @endif
                </div>
            </x-card>
        </div>
    </div>

    <!-- Загруженные фотографии -->
    @if($photos->total() > 0)
        <x-card class="mb-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-white">Загруженные фотографии ({{ $photos->total() }})</h3>
                <x-badge variant="success">{{ $photos->total() }} фото</x-badge>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 xl:grid-cols-12 gap-2">
                @foreach($photos as $photo)
                    <div class="relative aspect-square bg-gray-800 rounded-lg overflow-hidden group">
                        @php
                            $photoPath = $photo->original_path;
                            $fullPhotoPath = storage_path('app/public/' . $photoPath);
                            $photoExists = file_exists($fullPhotoPath);
                            $photoUrl = $photoExists 
                                ? Storage::url($photoPath) 
                                : (file_exists($fullPhotoPath) ? asset('storage/' . $photoPath) : null);
                        @endphp
                        @if($photoUrl && $photoExists)
                            <img src="{{ $photoUrl }}" alt="{{ $photo->original_name }}" class="w-full h-full object-cover">
                        @else
                            <div class="w-full h-full flex items-center justify-center text-gray-500 text-xs">
                                <div class="text-center">
                                    <p>Файл не найден</p>
                                    <p class="text-[10px] mt-1">{{ $photo->original_name }}</p>
                                </div>
                            </div>
                        @endif
                        <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-50 transition-opacity flex items-center justify-center opacity-0 group-hover:opacity-100">
                            <span class="text-white text-xs">{{ $photo->original_name }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
            
            <!-- Пагинация -->
            @if($photos->hasPages())
                <div class="mt-6">
                    {{ $photos->links() }}
                </div>
            @endif
            
            <div class="mt-4 p-4 bg-gray-800 rounded-lg">
                <p class="text-sm text-gray-300 mb-2">
                    <strong>Путь сохранения:</strong> <code class="text-xs bg-gray-900 px-2 py-1 rounded">storage/app/public/events/{{ $event->id }}/upload/</code>
                </p>
                <p class="text-sm text-gray-300">
                    <strong>Всего в БД:</strong> {{ $photos->total() }} записей
                    @if($photos->hasPages())
                        | Показано: {{ $photos->firstItem() }}-{{ $photos->lastItem() }} из {{ $photos->total() }}
                    @endif
                </p>
            </div>
        </x-card>
    @else
        <x-card class="mb-6">
            <div class="text-center py-8">
                <svg class="w-16 h-16 text-gray-600 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
                <p class="text-gray-400 mb-2">Фотографии еще не загружены</p>
                <p class="text-sm text-gray-500">Используйте кнопку "Загрузить фотографии" для начала работы</p>
            </div>
        </x-card>
    @endif

    @if($event->status === 'draft' || (auth()->user()->isAdmin() && $event->status !== 'published'))
        <!-- Модальное окно загрузки фотографий -->
        <x-modal id="upload-modal" title="Загрузить фотографии" size="lg">
            <form id="upload-form" enctype="multipart/form-data">
                @csrf
                <p class="text-sm text-gray-400 mb-4">Поддерживается загрузка от 1 до 15000 фотографий</p>
                <input type="file" name="photos[]" multiple accept="image/*" id="photos-input" class="w-full px-4 py-2 bg-[#121212] border border-gray-700 rounded-lg text-white mb-4" required>
                <div id="upload-progress" class="mt-4 hidden">
                    <div class="w-full bg-gray-700 rounded-full h-2.5">
                        <div id="progress-bar" class="bg-[#a78bfa] h-2.5 rounded-full transition-all" style="width: 0%"></div>
                    </div>
                    <p class="text-sm text-gray-400 mt-2">
                        Загружено: <span id="uploaded-count">0</span> / <span id="total-count">0</span>
                    </p>
                </div>
                <div class="flex space-x-3">
                    <x-button type="button" onclick="startUpload()" class="flex-1">Начать загрузку</x-button>
                    <x-button variant="outline" type="button" onclick="closeModal('upload-modal')">Отмена</x-button>
                </div>
            </form>
        </x-modal>
    @endif


    <!-- Модальное окно настройки анализа -->
            <x-modal id="analysis-modal" title="Настройка анализа" size="lg">
                <form action="{{ route('photo.events.start-analysis', $event->slug) }}" method="POST">
            @csrf
            
            <x-input label="Цена за фотографию (₽)" name="price" type="number" min="0" step="0.01" value="{{ $event->price }}" required />
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-300 mb-2">Типы анализа</label>
                <div class="space-y-2">
                    <!-- Timeline временно отключен -->
                    <!-- Скрытые поля для обязательных анализов -->
                    <input type="hidden" name="analyses[remove_exif]" value="1">
                    <input type="hidden" name="analyses[watermark]" value="1">
                    <x-checkbox label="Удаление EXIF данных" name="analyses[remove_exif]" :checked="true" :disabled="true" />
                    <x-checkbox label="Нанесение водяного знака" name="analyses[watermark]" :checked="true" :disabled="true" />
                    <x-checkbox label="Поиск лиц" name="analyses[face_search]" />
                    <!-- Поиск номеров временно отключен -->
                    <input type="hidden" name="analyses[number_search]" value="0">
                </div>
                <p class="text-xs text-gray-400 mt-2">Удаление EXIF и нанесение водяного знака выполняются всегда</p>
            </div>
            
            <div class="flex space-x-3">
                <x-button type="submit">Запустить анализ</x-button>
                <x-button variant="outline" type="button" onclick="closeModal('analysis-modal')">Отмена</x-button>
            </div>
        </form>
    </x-modal>
@endsection

@push('scripts')
<script>
function openAnalysisModal() {
    const modal = document.getElementById('analysis-modal');
    if (modal) {
        modal.classList.remove('hidden');
    } else {
        console.error('Модальное окно analysis-modal не найдено');
    }
}

function openUploadModal() {
    const modal = document.getElementById('upload-modal');
    if (modal) {
        modal.classList.remove('hidden');
        console.log('Модальное окно upload-modal открыто');
    } else {
        console.error('Модальное окно upload-modal не найдено');
        alert('Модальное окно загрузки не найдено. Убедитесь, что событие в статусе "черновик"');
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('hidden');
    }
}

// Загрузка фотографий
function startUpload() {
    const input = document.getElementById('photos-input');
    const files = input.files;
    
    if (files.length === 0) {
        alert('Выберите фотографии для загрузки');
        return;
    }
    
    if (files.length > 15000) {
        alert('Максимальное количество фотографий: 15000');
        return;
    }
    
    const formData = new FormData();
    for (let i = 0; i < files.length; i++) {
        formData.append('photos[]', files[i]);
    }
    formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);
    
    document.getElementById('upload-progress').classList.remove('hidden');
    const totalCount = files.length;
    document.getElementById('total-count').textContent = totalCount;
    document.getElementById('uploaded-count').textContent = '0';
    
    // Обновляем прогресс-бар (используем ID вместо класса с квадратными скобками)
    const progressBar = document.getElementById('progress-bar');
    if (progressBar) {
        progressBar.style.width = '0%';
    }
    
    // Добавляем CSRF токен в заголовки
    const csrfToken = document.querySelector('meta[name="csrf-token"]');
    if (!csrfToken) {
        alert('Ошибка: CSRF токен не найден. Перезагрузите страницу.');
        console.error('CSRF token not found');
        return;
    }
    
    console.log('Starting upload...', {
        filesCount: files.length,
        route: '{{ route("photo.events.upload", $event->slug) }}',
        csrfToken: csrfToken.content
    });
    
    // Используем XMLHttpRequest для отслеживания прогресса
    const xhr = new XMLHttpRequest();
    
    // Отслеживаем прогресс отправки
    xhr.upload.addEventListener('progress', function(e) {
        if (e.lengthComputable) {
            const percentComplete = (e.loaded / e.total) * 100;
            if (progressBar) {
                progressBar.style.width = percentComplete + '%';
            }
            // Показываем примерный прогресс загрузки (не обработки)
            // Реальный прогресс будет обновляться через polling
        }
    });
    
    // Отслеживаем прогресс обработки через polling
    let uploadedCount = 0;
    const progressInterval = setInterval(() => {
        // Получаем текущее количество загруженных фотографий через API
        fetch('{{ route("photo.events.upload-progress", $event->slug) }}', {
            method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken.content
            }
    })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.count > uploadedCount) {
                uploadedCount = data.count;
                document.getElementById('uploaded-count').textContent = uploadedCount;
                // Обновляем прогресс-бар на основе реального количества загруженных
                const realProgress = (uploadedCount / totalCount) * 100;
                if (progressBar) {
                    progressBar.style.width = Math.min(realProgress, 95) + '%';
                }
            }
        })
        .catch(err => console.error('Error polling progress:', err));
    }, 500); // Проверяем каждые 500мс
    
    xhr.addEventListener('load', function() {
        clearInterval(progressInterval);
        
        if (xhr.status >= 200 && xhr.status < 300) {
            try {
                const data = JSON.parse(xhr.responseText);
        console.log('Upload response:', data);
                
        if (data.success) {
                    // Устанавливаем финальный прогресс
                    document.getElementById('uploaded-count').textContent = data.uploaded || totalCount;
                    if (progressBar) {
                        progressBar.style.width = '100%';
                    }
                    
            console.log('Upload successful:', data);
            alert('Успешно загружено: ' + (data.uploaded || 0) + ' фотографий' + (data.errors > 0 ? '. Ошибок: ' + data.errors : ''));
            document.getElementById('upload-progress').classList.add('hidden');
            closeModal('upload-modal');
            // Очищаем input
            document.getElementById('photos-input').value = '';
            location.reload();
        } else {
            console.error('Upload failed:', data);
            let errorMsg = data.message || 'Неизвестная ошибка';
            if (data.debug) {
                console.error('Debug info:', data.debug);
                errorMsg += '\n\nОтладочная информация:\n' + JSON.stringify(data.debug, null, 2);
            }
            if (data.error_details && data.error_details.length > 0) {
                errorMsg += '\n\nДетали ошибок:\n' + data.error_details.slice(0, 5).map(e => '- ' + (e.file || 'неизвестный файл') + ': ' + e.error).join('\n');
            }
            alert('Ошибка: ' + errorMsg);
            document.getElementById('upload-progress').classList.add('hidden');
        }
            } catch (e) {
                console.error('Failed to parse response:', e);
                alert('Ошибка: Не удалось обработать ответ сервера');
                document.getElementById('upload-progress').classList.add('hidden');
            }
        } else {
            console.error('Upload failed with status:', xhr.status);
            try {
                const errorData = JSON.parse(xhr.responseText);
                alert('Ошибка: ' + (errorData.message || 'Неизвестная ошибка'));
            } catch (e) {
                alert('Ошибка сервера: ' + xhr.status);
            }
            document.getElementById('upload-progress').classList.add('hidden');
        }
    });
    
    xhr.addEventListener('error', function() {
        clearInterval(progressInterval);
        console.error('Upload error');
        alert('Произошла ошибка при загрузке. Проверьте консоль браузера для деталей.');
        document.getElementById('upload-progress').classList.add('hidden');
    });
    
    xhr.open('POST', '{{ route("photo.events.upload", $event->slug) }}');
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    xhr.setRequestHeader('X-CSRF-TOKEN', csrfToken.content);
    xhr.send(formData);
}

// Polling для обновления статуса анализа
let analysisStatusPollingInterval = null;

function updateAnalysisStatus() {
    const statusCard = document.getElementById('analysis-status-card');
    if (!statusCard) {
        console.log('Analysis status card not found, stopping polling');
        if (analysisStatusPollingInterval) {
            clearInterval(analysisStatusPollingInterval);
            analysisStatusPollingInterval = null;
        }
        return; // Блок статуса не найден, прекращаем polling
    }

    console.log('Updating analysis status...');
    
    fetch('{{ route("photo.events.status", $event->slug) }}', {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        },
        credentials: 'same-origin'
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        console.log('Analysis status updated:', data);
        
        // Обновляем статус события
        const eventStatus = data.event_status;
        if (eventStatus) {
            // Можно обновить статус события на странице, если нужно
            console.log('Event status:', eventStatus);
        }
        
        // Обновляем статусы задач
        if (data.tasks && Array.isArray(data.tasks)) {
            console.log('Updating tasks:', data.tasks);
            
            data.tasks.forEach(task => {
                const taskType = task.type;
                const status = task.status;
                const progress = task.progress || 0;
                const completed = task.completed || 0;
                const total = task.total || 0;
                
                console.log('Processing task:', { taskType, status, progress, completed, total });
                
                // Обновляем статус
                const statusBadge = document.getElementById('task-status-' + taskType);
                const statusText = document.getElementById('task-status-text-' + taskType);
                if (statusBadge && statusText) {
                    let variant = 'default';
                    let text = 'Ожидание';
                    
                    if (status === 'completed') {
                        variant = 'success';
                        text = 'Завершено';
                    } else if (status === 'processing') {
                        variant = 'warning';
                        text = 'В процессе';
                    }
                    
                    // Обновляем класс badge
                    statusBadge.className = statusBadge.className.replace(/bg-\w+-\d+|text-\w+-\d+/g, '');
                    if (variant === 'success') {
                        statusBadge.classList.add('bg-green-500', 'text-white');
                    } else if (variant === 'warning') {
                        statusBadge.classList.add('bg-yellow-500', 'text-white');
                    } else {
                        statusBadge.classList.add('bg-gray-600', 'text-gray-300');
                    }
                    statusText.textContent = text;
                    
                    console.log('Status updated:', { taskType, variant, text });
                } else {
                    console.warn('Status elements not found for task:', taskType);
                }
                
                // Обновляем прогресс
                const progressBar = document.getElementById('task-progress-bar-' + taskType);
                const progressText = document.getElementById('task-progress-text-' + taskType);
                const totalPhotosEl = document.getElementById('task-total-' + taskType);
                // Используем данные из ответа, если они есть, иначе вычисляем
                const totalPhotos = total > 0 ? total : (totalPhotosEl ? parseInt(totalPhotosEl.textContent) || 0 : 0);
                const processedCount = completed > 0 ? completed : (totalPhotos > 0 ? Math.round((progress / 100) * totalPhotos) : 0);
                
                if (progressBar) {
                    progressBar.style.width = progress + '%';
                    console.log('Progress bar updated:', { taskType, progress, processed: processedCount, total: totalPhotos });
                }
                if (progressText) {
                    progressText.textContent = progress;
                }
                const processedEl = document.getElementById('task-processed-' + taskType);
                if (processedEl) {
                    processedEl.textContent = processedCount;
                }
                if (totalPhotosEl && total > 0) {
                    totalPhotosEl.textContent = totalPhotos;
                }
                
                console.log('Progress updated:', { taskType, progress, processed: processedCount, total: totalPhotos, completed, totalFromResponse: total });
            });
        } else {
            console.warn('No tasks data in response:', data);
        }
        
        // Если все задачи завершены, останавливаем polling
        const allCompleted = data.tasks && data.tasks.every(task => task.status === 'completed');
        if (allCompleted && analysisStatusPollingInterval) {
            clearInterval(analysisStatusPollingInterval);
            analysisStatusPollingInterval = null;
            console.log('All tasks completed, polling stopped');
        }
    })
    .catch(error => {
        console.error('Error updating analysis status:', error);
    });
}

// Запускаем polling всегда, если есть блок статуса анализа на странице
// Это позволит обновлять статусы даже если задачи еще не созданы или событие уже опубликовано
function initAnalysisStatusPolling() {
    // Пробуем найти блок несколько раз с задержкой (на случай, если DOM еще не полностью загружен)
    let attempts = 0;
    const maxAttempts = 10;
    
    function tryFindCard() {
        attempts++;
        // Пробуем найти по нескольким вариантам ID
        let statusCard = document.getElementById('analysis-status-card');
        if (!statusCard) {
            // Если не нашли по основному ID, пробуем найти через wrapper или content
            const wrapper = document.getElementById('analysis-status-card-wrapper');
            const content = document.getElementById('analysis-status-content');
            if (wrapper) {
                statusCard = wrapper;
            } else if (content) {
                statusCard = content.parentElement;
            }
        }
        
        if (statusCard) {
            console.log('Analysis status card found (attempt ' + attempts + '), starting polling...');
            console.log('Status card element:', statusCard);
            console.log('Status card id:', statusCard.id);
            console.log('Status card visible:', statusCard.offsetParent !== null);
            console.log('Status card display:', window.getComputedStyle(statusCard).display);
            
            // Обновляем статус сразу при загрузке страницы
            updateAnalysisStatus();
            
            // Затем обновляем каждые 5 секунд
            analysisStatusPollingInterval = setInterval(function() {
                // Проверяем, что блок статуса все еще существует
                let card = document.getElementById('analysis-status-card');
                if (!card) {
                    const wrapper = document.getElementById('analysis-status-card-wrapper');
                    const content = document.getElementById('analysis-status-content');
                    if (wrapper) {
                        card = wrapper;
                    } else if (content) {
                        card = content.parentElement;
                    }
                }
                
                if (card) {
                    updateAnalysisStatus();
                } else {
                    // Если блока нет, останавливаем polling
                    if (analysisStatusPollingInterval) {
                        clearInterval(analysisStatusPollingInterval);
                        analysisStatusPollingInterval = null;
                        console.log('Analysis status card removed, polling stopped');
                    }
                }
            }, 5000);
            
            console.log('Polling started, interval:', analysisStatusPollingInterval);
            return true;
        } else if (attempts < maxAttempts) {
            console.log('Analysis status card not found (attempt ' + attempts + '), retrying...');
            setTimeout(tryFindCard, 500); // Повторяем через 500мс
            return false;
        } else {
            console.error('Analysis status card not found after ' + maxAttempts + ' attempts, polling not started');
            console.log('Available elements with "analysis" in id:', 
                Array.from(document.querySelectorAll('[id*="analysis"]')).map(el => ({id: el.id, tag: el.tagName})));
            console.log('Available elements with "status" in id:', 
                Array.from(document.querySelectorAll('[id*="status"]')).map(el => ({id: el.id, tag: el.tagName})));
            // Пробуем найти через content элемент
            const content = document.getElementById('analysis-status-content');
            if (content) {
                console.log('Found analysis-status-content, using parent element for polling');
                // Используем content элемент для polling
                updateAnalysisStatus();
                analysisStatusPollingInterval = setInterval(updateAnalysisStatus, 5000);
                console.log('Polling started using content element, interval:', analysisStatusPollingInterval);
                return true;
            }
            return false;
        }
    }
    
    tryFindCard();
}

// Запускаем при загрузке DOM
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
        initAnalysisStatusPolling();
        // Убеждаемся, что функции доступны глобально
        window.startUpload = startUpload;
        window.openUploadModal = openUploadModal;
        window.openAnalysisModal = openAnalysisModal;
        window.closeModal = closeModal;
    });
} else {
    // DOM уже загружен
    initAnalysisStatusPolling();
    // Убеждаемся, что функции доступны глобально
    window.startUpload = startUpload;
    window.openUploadModal = openUploadModal;
    window.openAnalysisModal = openAnalysisModal;
    window.closeModal = closeModal;
}

// Также делаем функции глобально доступными сразу
window.startUpload = startUpload;
window.openUploadModal = openUploadModal;
window.openAnalysisModal = openAnalysisModal;
window.closeModal = closeModal;
</script>
@endpush


