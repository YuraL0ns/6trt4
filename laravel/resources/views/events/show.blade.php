@extends('layouts.app')

@section('title', $event->title . ' - Hunter-Photo.Ru')
@section('page-title', $event->title)
@section('page-description', $event->city . ', ' . $event->getFormattedDate())

@php
    use Illuminate\Support\Facades\Storage;
@endphp

@section('content')
    <!-- Название события и найденные номера (50/50) -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <!-- Левая половина: Информация о событии -->
        <div>
            <x-card>
                <h1 class="text-2xl font-bold text-white mb-4">{{ $event->title }}</h1>
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <p class="text-sm text-gray-400 mb-1">{{ $event->city }}</p>
                        <p class="text-sm text-gray-400">{{ $event->getFormattedDate() }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm text-gray-400">Фотограф</p>
                        <p class="font-semibold text-white">{{ $event->author->full_name }}</p>
                    </div>
                </div>
                
                @if($event->description)
                    <p class="text-gray-300 mb-4">{{ $event->description }}</p>
                @endif
            </x-card>
        </div>

        <!-- Правая половина: Найденные номера (только если был выбран анализ number_search) -->
        @if(!empty($allNumbers))
            @php
                // Проверяем, был ли выбран анализ number_search для этого события
                $hasNumberSearch = false;
                try {
                    $eventInfoPath = storage_path('app/public/events/' . $event->id . '/event_info.json');
                    if (file_exists($eventInfoPath)) {
                        $eventInfo = json_decode(file_get_contents($eventInfoPath), true);
                        $hasNumberSearch = isset($eventInfo['analyze']['number_search']) && $eventInfo['analyze']['number_search'];
                    }
                } catch (\Exception $e) {
                    // Если не удалось прочитать файл, показываем блок по умолчанию
                    $hasNumberSearch = true;
                }
            @endphp
            @if($hasNumberSearch)
                <x-card>
                    <h3 class="text-lg font-semibold text-white mb-4">Найденные номера</h3>
                    <div class="flex flex-wrap gap-2">
                        @foreach($allNumbers as $number)
                            <button 
                                onclick="filterByNumber('{{ $number }}')" 
                                class="px-4 py-2 bg-gray-700 hover:bg-[#a78bfa] text-white rounded-lg transition-colors number-filter-btn"
                                data-number="{{ $number }}"
                            >
                                {{ $number }}
                            </button>
                        @endforeach
                        <button 
                            onclick="clearNumberFilter()" 
                            class="px-4 py-2 bg-gray-600 hover:bg-gray-500 text-white rounded-lg transition-colors"
                            id="clear-filter-btn"
                            style="display: none;"
                        >
                            Сбросить фильтр
                        </button>
                    </div>
                </x-card>
            @endif
        @endif
    </div>

    <!-- Обложка и поиск по селфи (50/50) -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <!-- Обложка события -->
        @if($event->cover_path)
            <x-card>
                <h3 class="text-lg font-semibold text-white mb-4">Обложка события</h3>
                <div class="aspect-video bg-gray-800 rounded-lg overflow-hidden">
                    @php
                        $coverPath = $event->cover_path;
                        $fullCoverPath = storage_path('app/public/' . $coverPath);
                        $coverExists = file_exists($fullCoverPath);
                        $coverUrl = $coverExists ? Storage::url($coverPath) : null;
                    @endphp
                    @if($coverUrl && $coverExists)
                        <img src="{{ $coverUrl }}" alt="{{ $event->title }}" class="w-full h-full object-cover">
                    @else
                        <div class="w-full h-full flex items-center justify-center text-gray-400">
                            Обложка не найдена
                        </div>
                    @endif
                </div>
            </x-card>
        @endif

        <!-- Поиск по селфи -->
        <x-card title="Найти себя на фотографиях">
            <form id="face-search-form" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">
                        Загрузите ваше селфи
                    </label>
                    <input type="file" name="photo" id="face-search-photo" accept="image/*" class="w-full px-4 py-2 bg-[#121212] border border-gray-700 rounded-lg text-white" required>
                </div>
                <x-button type="submit" id="face-search-submit">Найти похожие фотографии</x-button>
            </form>
            <div id="face-search-status" class="mt-4 hidden">
                <div class="text-sm text-gray-400" id="face-search-message"></div>
                <div class="mt-2 hidden" id="face-search-progress">
                    <div class="w-full bg-gray-700 rounded-full h-2">
                        <div class="bg-[#a78bfa] h-2 rounded-full transition-all duration-300" style="width: 0%" id="face-search-progress-bar"></div>
                    </div>
                </div>
            </div>
        </x-card>
    </div>

    <!-- Фильтр по времени (если есть EXIF данные) -->
    @if($hasTimeline && $minTime && $maxTime)
        <x-card class="mb-6">
            <h3 class="text-lg font-semibold text-white mb-4">Фильтр по времени</h3>
            <div class="space-y-4">
                <div class="flex items-center justify-between text-sm text-gray-400 mb-4">
                    <span id="time-range-display">Весь период</span>
                    <button onclick="clearTimeFilter()" class="text-[#a78bfa] hover:text-[#8b5cf6] transition-colors" id="clear-time-filter-btn" style="display: none;">
                        Сбросить
                    </button>
                </div>
                
                <!-- Визуальная временная шкала с двумя кружками -->
                <div class="relative w-full" style="overflow: visible;">
                    <!-- Фоновая линия -->
                    <div class="relative w-full h-2 bg-gray-700 rounded-full" style="margin: 0 8px;">
                        <!-- Активная область между кружками -->
                        <div id="time-range-active" class="absolute h-2 bg-[#a78bfa] rounded-full" style="left: 0%; width: 100%;"></div>
                        
                        <!-- Скрытые ползунки для функциональности -->
                        <input type="range" id="time-slider-min" min="0" max="1440" value="0" class="absolute top-0 left-0 w-full h-2 bg-transparent appearance-none cursor-pointer opacity-0 z-10" oninput="updateTimeFilter()">
                        <input type="range" id="time-slider-max" min="0" max="1440" value="1440" class="absolute top-0 left-0 w-full h-2 bg-transparent appearance-none cursor-pointer opacity-0 z-20" oninput="updateTimeFilter()">
                        
                        <!-- Кружок для минимального времени -->
                        <div id="time-handle-min" class="absolute top-1/2 w-4 h-4 bg-[#a78bfa] rounded-full border-2 border-white shadow-lg cursor-grab active:cursor-grabbing z-30" style="left: 0%; transform: translate(-50%, -50%);">
                            <div class="absolute top-full left-1/2 -translate-x-1/2 mt-2 px-2 py-1 bg-[#1a1a1a] border border-gray-700 rounded text-xs text-white whitespace-nowrap pointer-events-none" id="time-tooltip-min">
                                {{ $minTime->format('H:i') }}
                            </div>
                        </div>
                        
                        <!-- Кружок для максимального времени -->
                        <div id="time-handle-max" class="absolute top-1/2 w-4 h-4 bg-[#a78bfa] rounded-full border-2 border-white shadow-lg cursor-grab active:cursor-grabbing z-30" style="right: 0%; transform: translate(50%, -50%);">
                            <div class="absolute top-full left-1/2 -translate-x-1/2 mt-2 px-2 py-1 bg-[#1a1a1a] border border-gray-700 rounded text-xs text-white whitespace-nowrap pointer-events-none" id="time-tooltip-max">
                                {{ $maxTime->format('H:i') }}
                            </div>
                        </div>
                    </div>
                    
                    <!-- Подписи времени -->
                    <div class="flex justify-between text-xs text-gray-500 mt-2">
                        <span id="time-min-display">{{ $minTime->format('H:i') }}</span>
                        <span id="time-max-display">{{ $maxTime->format('H:i') }}</span>
                    </div>
                </div>
            </div>
        </x-card>
    @endif

    <!-- Галерея фотографий -->
    <div>
        <h2 class="text-2xl font-bold text-white mb-4">Фотографии</h2>
        
        @if($photos->count() > 0)
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-4" id="photos-grid">
                @foreach($photos as $photo)
                    @php
                        // Получаем время из EXIF для фильтрации
                        $photoTime = null;
                        if ($photo->created_at_exif) {
                            try {
                                $photoTime = \Carbon\Carbon::parse($photo->created_at_exif);
                            } catch (\Exception $e) {
                                $photoTime = null;
                            }
                        }
                        // Преобразуем время в минуты от начала дня (0-1440)
                        $photoTimeMinutes = $photoTime ? ($photoTime->hour * 60 + $photoTime->minute) : null;
                    @endphp
                    <div 
                        class="group relative aspect-square bg-gray-800 rounded-lg overflow-hidden cursor-pointer photo-item" 
                        onclick="openPhotoModal('{{ $photo->id }}')"
                        data-photo-id="{{ $photo->id }}"
                        data-numbers="{{ json_encode($photo->numbers ?? []) }}"
                        @if($photoTimeMinutes !== null)
                            data-time-minutes="{{ $photoTimeMinutes }}"
                        @endif
                    >
                        @php
                            $photoUrl = $photo->getDisplayUrl();
                            $fallbackUrl = $photo->getFallbackUrl();
                            // Если есть S3 URL, используем его как основной, иначе используем локальный
                            $primaryUrl = $photo->s3_custom_url ?: ($photo->custom_path ? Storage::url($photo->custom_path) : ($photo->original_path ? Storage::url($photo->original_path) : null));
                            $secondaryUrl = $fallbackUrl ?: ($photo->custom_path ? Storage::url($photo->custom_path) : ($photo->original_path ? Storage::url($photo->original_path) : null));
                        @endphp
                        @if($primaryUrl)
                            <img 
                                src="{{ $primaryUrl }}" 
                                alt="Photo" 
                                class="w-full h-full object-cover group-hover:opacity-75 transition-opacity"
                                @if($secondaryUrl && $secondaryUrl !== $primaryUrl)
                                    onerror="this.onerror=null; this.src='{{ $secondaryUrl }}';"
                                @else
                                    onerror="this.onerror=null; this.style.display='none'; this.nextElementSibling.style.display='flex';"
                                @endif
                            >
                            <div class="w-full h-full flex items-center justify-center text-gray-500 text-xs" style="display: none;">
                                <p>Изображение не загружено</p>
                            </div>
                        @else
                            <div class="w-full h-full flex items-center justify-center text-gray-500 text-xs">
                                <p>Изображение не найдено</p>
                            </div>
                        @endif
                        <div class="absolute inset-0 bg-black/0 group-hover:bg-black/50 transition-all flex items-center justify-center">
                            <div class="opacity-0 group-hover:opacity-100 transition-opacity text-white text-center">
                                <p class="font-semibold">{{ number_format($photo->getPriceWithCommission(), 0, ',', ' ') }} ₽</p>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-6">
                {{ $photos->links() }}
            </div>
        @else
            <x-empty-state 
                title="Фотографии не найдены" 
                description="В этом событии пока нет фотографий"
            />
        @endif
    </div>

    <!-- Модальное окно просмотра фотографии -->
    <x-modal id="photo-modal" title="Просмотр фотографии" size="lg">
        <div class="relative">
            <!-- Кнопка "Назад" -->
            <button id="prev-photo-btn" onclick="navigatePhoto(-1)" class="hidden absolute left-0 top-1/2 -translate-y-1/2 z-10 bg-black/50 hover:bg-black/70 text-white p-3 rounded-full transition-all">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
            </button>
            
            <!-- Контент будет загружен динамически -->
            <div id="photo-modal-content"></div>
            
            <!-- Кнопка "Вперед" -->
            <button id="next-photo-btn" onclick="navigatePhoto(1)" class="hidden absolute right-0 top-1/2 -translate-y-1/2 z-10 bg-black/50 hover:bg-black/70 text-white p-3 rounded-full transition-all">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                </svg>
            </button>
        </div>
    </x-modal>
@endsection

@push('scripts')
<script>
// Глобальные переменные для навигации
let currentPhotoIndex = -1;
let photoIds = @json($photos->pluck('id')->toArray());
let isLoadingPhoto = false; // Флаг для предотвращения множественных загрузок

function openPhotoModal(photoId) {
    // Находим индекс текущей фотографии
    currentPhotoIndex = photoIds.indexOf(photoId);
    if (currentPhotoIndex === -1) {
        console.error('Photo ID not found in list');
        return;
    }
    
    loadPhoto(photoId);
}

function loadPhoto(photoId) {
    // Предотвращаем множественные загрузки
    if (isLoadingPhoto) {
        return;
    }
    
    isLoadingPhoto = true;
    
    // Показываем индикатор загрузки
    document.getElementById('photo-modal-content').innerHTML = `
        <div class="flex items-center justify-center h-64">
            <div class="text-center">
                <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-[#a78bfa] mb-2"></div>
                <p class="text-gray-400">Загрузка...</p>
            </div>
        </div>
    `;
    
    // Блокируем кнопки навигации во время загрузки
    const prevBtn = document.getElementById('prev-photo-btn');
    const nextBtn = document.getElementById('next-photo-btn');
    if (prevBtn) prevBtn.style.pointerEvents = 'none';
    if (nextBtn) nextBtn.style.pointerEvents = 'none';
    
    // Загрузка данных фотографии и открытие модального окна
    fetch(`/events/{{ $event->slug ?? $event->id }}/photo/${photoId}`, {
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
        .then(async response => {
            if (!response.ok) {
                const text = await response.text();
                throw new Error(`HTTP ${response.status}: ${text.substring(0, 100)}`);
            }
            return response.json();
        })
        .then(data => {
            const fallbackUrl = data.fallback_url || null;
            const imgTag = fallbackUrl && fallbackUrl !== data.url
                ? `<img src="${data.url}" alt="Photo" class="w-full rounded-lg mb-4" onerror="if(this.src !== '${fallbackUrl}') { this.src='${fallbackUrl}'; } else { this.style.display='none'; this.nextElementSibling.style.display='flex'; }">`
                : `<img src="${data.url}" alt="Photo" class="w-full rounded-lg mb-4" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">`;
            
            const errorDiv = `<div class="w-full h-64 flex items-center justify-center text-gray-500" style="display: none;"><p>Изображение не загружено</p></div>`;
            
            // Определяем кнопку корзины в зависимости от статуса
            let cartButton = '';
            if (data.in_cart) {
                cartButton = `<a href="{{ route('cart.index') }}" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white font-semibold rounded-lg transition-colors inline-block text-center">
                    Уже в корзине
                </a>`;
            } else {
                cartButton = `<button onclick="addToCart('${photoId}')" class="px-4 py-2 bg-[#a78bfa] hover:bg-[#8b5cf6] text-white font-semibold rounded-lg transition-colors">
                    Добавить в корзину
                </button>`;
            }
            
            document.getElementById('photo-modal-content').innerHTML = `
                ${imgTag}
                ${errorDiv}
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <p class="text-2xl font-bold text-white">${data.price} ₽</p>
                        ${data.numbers ? `<p class="text-sm text-gray-400 mt-1">Номера: ${data.numbers.join(', ')}</p>` : ''}
                    </div>
                    ${cartButton}
                </div>
                <div class="flex space-x-2">
                    ${data.has_faces ? '<button onclick="findSimilar(\'' + photoId + '\', \'face\')" class="px-4 py-2 border border-gray-700 hover:bg-gray-800 text-white font-semibold rounded-lg transition-colors">Показать похожие (по лицам)</button>' : ''}
                    ${data.numbers ? '<button onclick="findSimilar(\'' + photoId + '\', \'number\')" class="px-4 py-2 border border-gray-700 hover:bg-gray-800 text-white font-semibold rounded-lg transition-colors">Показать похожие (по номерам)</button>' : ''}
                </div>
            `;
            
            // Показываем/скрываем кнопки навигации
            const prevBtn = document.getElementById('prev-photo-btn');
            const nextBtn = document.getElementById('next-photo-btn');
            
            if (currentPhotoIndex > 0) {
                prevBtn.classList.remove('hidden');
            } else {
                prevBtn.classList.add('hidden');
            }
            
            if (currentPhotoIndex < photoIds.length - 1) {
                nextBtn.classList.remove('hidden');
            } else {
                nextBtn.classList.add('hidden');
            }
            
            document.getElementById('photo-modal').classList.remove('hidden');
            
            // Разблокируем кнопки навигации после успешной загрузки
            isLoadingPhoto = false;
            if (prevBtn) prevBtn.style.pointerEvents = 'auto';
            if (nextBtn) nextBtn.style.pointerEvents = 'auto';
        })
        .catch(error => {
            isLoadingPhoto = false;
            console.error('Ошибка при загрузке данных фотографии:', error);
            
            // Разблокируем кнопки навигации
            const prevBtn = document.getElementById('prev-photo-btn');
            const nextBtn = document.getElementById('next-photo-btn');
            if (prevBtn) prevBtn.style.pointerEvents = 'auto';
            if (nextBtn) nextBtn.style.pointerEvents = 'auto';
            
            // Показываем ошибку
            document.getElementById('photo-modal-content').innerHTML = `
                <div class="flex items-center justify-center h-64">
                    <div class="text-center">
                        <p class="text-red-400 mb-2">Ошибка при загрузке фотографии</p>
                        <p class="text-gray-400 text-sm">${error.message}</p>
                    </div>
                </div>
            `;
        });
}

function navigatePhoto(direction) {
    // Предотвращаем навигацию во время загрузки
    if (isLoadingPhoto) {
        return;
    }
    
    const newIndex = currentPhotoIndex + direction;
    if (newIndex >= 0 && newIndex < photoIds.length) {
        currentPhotoIndex = newIndex;
        loadPhoto(photoIds[currentPhotoIndex]);
    }
}

// Обработка нажатий клавиш для навигации
document.addEventListener('keydown', function(e) {
    const modal = document.getElementById('photo-modal');
    if (!modal || modal.classList.contains('hidden')) {
        return;
    }
    
    if (e.key === 'ArrowLeft') {
        navigatePhoto(-1);
    } else if (e.key === 'ArrowRight') {
        navigatePhoto(1);
    } else if (e.key === 'Escape') {
        modal.classList.add('hidden');
    }
});

function addToCart(photoId) {
    fetch('/cart', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        },
        body: JSON.stringify({ photo_id: photoId })
    })
    .then(async response => {
        // Проверяем тип ответа
        const contentType = response.headers.get('content-type');
        if (contentType && contentType.includes('application/json')) {
            return response.json();
        } else {
            // Если ответ не JSON, пробуем прочитать как текст
            const text = await response.text();
            throw new Error('Ожидался JSON ответ, получен: ' + text.substring(0, 100));
        }
    })
    .then(data => {
        if (data.success) {
            // Обновляем кнопку на "Уже в корзине" и перезагружаем данные фото
            loadPhoto(photoId);
            // Можно обновить счетчик корзины если он есть
            if (typeof updateCartCount === 'function') {
                updateCartCount();
            }
        } else {
            // Если фото уже в корзине, обновляем кнопку и показываем сообщение
            if (data.message && data.message.includes('уже в корзине')) {
                loadPhoto(photoId); // Перезагружаем данные фото для обновления кнопки
            } else {
                alert(data.message || 'Ошибка при добавлении в корзину');
            }
        }
    })
    .catch(error => {
        console.error('Ошибка при добавлении в корзину:', error);
        alert('Ошибка при добавлении в корзину: ' + error.message);
    });
}

function findSimilar(photoId, type) {
    // Реализация поиска похожих фотографий
    // TODO: Реализовать поиск по существующей фотографии
    alert('Функция поиска по существующей фотографии будет реализована позже');
}

// Обработка формы поиска по селфи
document.addEventListener('DOMContentLoaded', function() {
    const faceSearchForm = document.getElementById('face-search-form');
    if (faceSearchForm) {
        faceSearchForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData();
            const photoInput = document.getElementById('face-search-photo');
            const submitBtn = document.getElementById('face-search-submit');
            const statusDiv = document.getElementById('face-search-status');
            const messageDiv = document.getElementById('face-search-message');
            const progressDiv = document.getElementById('face-search-progress');
            const progressBar = document.getElementById('face-search-progress-bar');
            
            if (!photoInput.files || !photoInput.files[0]) {
                alert('Пожалуйста, выберите фотографию');
                return;
            }
            
            formData.append('photo', photoInput.files[0]);
            formData.append('type', 'face');
            formData.append('_token', document.querySelector('input[name="_token"]').value);
            
            // Показываем статус
            statusDiv.classList.remove('hidden');
            messageDiv.textContent = 'Отправка запроса...';
            messageDiv.classList.remove('text-red-400', 'text-yellow-400', 'text-green-400');
            progressDiv.classList.add('hidden');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Поиск...';
            
            try {
                const response = await fetch('{{ route("events.find-similar", $event->slug) }}', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                
                const data = await response.json();
                
                if (!response.ok) {
                    throw new Error(data.error || 'Ошибка при выполнении поиска');
                }
                
                if (data.status === 'processing' && data.task_id) {
                    // Задача запущена асинхронно, нужно опрашивать статус
                    messageDiv.textContent = 'Поиск выполняется, пожалуйста подождите...';
                    progressDiv.classList.remove('hidden');
                    progressBar.style.width = '50%';
                    
                    await pollSearchTask(data.task_id);
                } else if (data.status === 'completed') {
                    // Результаты готовы сразу
                    showSearchResults(data.results || [], data.total || 0);
                } else {
                    throw new Error('Неизвестный статус ответа');
                }
            } catch (error) {
                console.error('Ошибка поиска:', error);
                messageDiv.textContent = 'Ошибка: ' + error.message;
                messageDiv.classList.add('text-red-400');
                progressDiv.classList.add('hidden');
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Найти похожие фотографии';
            }
        });
    }
});

// Опрос статуса асинхронной задачи
async function pollSearchTask(taskId) {
    const messageDiv = document.getElementById('face-search-message');
    const progressBar = document.getElementById('face-search-progress-bar');
    
    const maxAttempts = 60; // Максимум 60 попыток (5 минут при интервале 5 сек)
    let attempts = 0;
    
    const poll = async () => {
        try {
            // Используем Laravel маршрут для проверки статуса задачи
            const response = await fetch(`/api/search-task/${taskId}/status`);
            const data = await response.json();
            
            if (data.status === 'completed') {
                progressBar.style.width = '100%';
                messageDiv.textContent = 'Поиск завершен!';
                messageDiv.classList.remove('text-red-400', 'text-yellow-400');
                messageDiv.classList.add('text-green-400');
                
                // Получаем результаты
                if (data.result && data.result.results) {
                    showSearchResults(data.result.results, data.result.total_found || 0);
                } else {
                    showSearchResults([], 0);
                }
                return;
            } else if (data.status === 'failed') {
                throw new Error(data.error || 'Задача завершилась с ошибкой');
            } else {
                // Задача еще выполняется
                attempts++;
                const progress = Math.min(50 + (attempts / maxAttempts) * 50, 95);
                progressBar.style.width = progress + '%';
                
                if (attempts < maxAttempts) {
                    setTimeout(poll, 5000); // Опрашиваем каждые 5 секунд
                } else {
                    throw new Error('Превышено время ожидания');
                }
            }
        } catch (error) {
            console.error('Ошибка опроса задачи:', error);
            messageDiv.textContent = 'Ошибка: ' + error.message;
            messageDiv.classList.add('text-red-400');
        }
    };
    
    poll();
}

// Отображение результатов поиска
function showSearchResults(results, total) {
    const messageDiv = document.getElementById('face-search-message');
    const progressDiv = document.getElementById('face-search-progress');
    const progressBar = document.getElementById('face-search-progress-bar');
    
    progressBar.style.width = '100%';
    progressDiv.classList.add('hidden');
    
    if (total === 0) {
        messageDiv.textContent = 'Похожих фотографий не найдено';
        messageDiv.classList.remove('text-red-400', 'text-green-400');
        messageDiv.classList.add('text-yellow-400');
        // Сбрасываем подсветку
        document.querySelectorAll('.photo-item').forEach(item => {
            item.classList.remove('ring-4', 'ring-[#a78bfa]');
        });
        return;
    }
    
    messageDiv.textContent = `Найдено похожих фотографий: ${total}`;
    messageDiv.classList.remove('text-red-400', 'text-yellow-400');
    messageDiv.classList.add('text-green-400');
    
    // Получаем ID найденных фотографий
    const foundPhotoIds = results.map(r => String(r.photo_id || r.id || '')).filter(Boolean);
    
    // Подсвечиваем найденные фотографии
    let firstFound = false;
    document.querySelectorAll('.photo-item').forEach(item => {
        const photoId = String(item.dataset.photoId || '');
        if (foundPhotoIds.includes(photoId)) {
            item.classList.add('ring-4', 'ring-[#a78bfa]');
            // Прокручиваем к первой найденной фотографии
            if (!firstFound) {
                item.scrollIntoView({ behavior: 'smooth', block: 'center' });
                firstFound = true;
            }
        } else {
            item.classList.remove('ring-4', 'ring-[#a78bfa]');
        }
    });
}

// Фильтрация по номерам
let currentFilterNumber = null;

function filterByNumber(number) {
    currentFilterNumber = number;
    
    // Обновляем стили кнопок
    document.querySelectorAll('.number-filter-btn').forEach(btn => {
        if (btn.dataset.number === number) {
            btn.classList.add('bg-[#a78bfa]');
            btn.classList.remove('bg-gray-700');
        } else {
            btn.classList.remove('bg-[#a78bfa]');
            btn.classList.add('bg-gray-700');
        }
    });
    
    // Показываем кнопку сброса фильтра
    document.getElementById('clear-filter-btn').style.display = 'inline-block';
    
    // Фильтруем фотографии
    document.querySelectorAll('.photo-item').forEach(item => {
        const numbers = JSON.parse(item.dataset.numbers || '[]');
        const hasNumber = numbers.includes(number);
        
        if (hasNumber) {
            item.style.display = 'block';
        } else {
            item.style.display = 'none';
        }
    });
}

function clearNumberFilter() {
    currentFilterNumber = null;
    
    // Сбрасываем стили кнопок
    document.querySelectorAll('.number-filter-btn').forEach(btn => {
        btn.classList.remove('bg-[#a78bfa]');
        btn.classList.add('bg-gray-700');
    });
    
    // Скрываем кнопку сброса фильтра
    document.getElementById('clear-filter-btn').style.display = 'none';
    
    // Показываем все фотографии
    document.querySelectorAll('.photo-item').forEach(item => {
        item.style.display = 'block';
    });
}

// Фильтр по времени
@if($hasTimeline && $minTime && $maxTime)
let currentTimeFilter = { min: 0, max: 1440 };

function updateTimeFilter() {
    const minSlider = document.getElementById('time-slider-min');
    const maxSlider = document.getElementById('time-slider-max');
    const minDisplay = document.getElementById('time-min-display');
    const maxDisplay = document.getElementById('time-max-display');
    const rangeDisplay = document.getElementById('time-range-display');
    const clearBtn = document.getElementById('clear-time-filter-btn');
    const minHandle = document.getElementById('time-handle-min');
    const maxHandle = document.getElementById('time-handle-max');
    const activeRange = document.getElementById('time-range-active');
    const minTooltip = document.getElementById('time-tooltip-min');
    const maxTooltip = document.getElementById('time-tooltip-max');
    
    let min = parseInt(minSlider.value);
    let max = parseInt(maxSlider.value);
    
    // Убеждаемся, что min <= max
    if (min > max) {
        min = max;
        minSlider.value = min;
    }
    
    currentTimeFilter.min = min;
    currentTimeFilter.max = max;
    
    // Обновляем отображение времени
    const minHours = Math.floor(min / 60);
    const minMinutes = min % 60;
    const maxHours = Math.floor(max / 60);
    const maxMinutes = max % 60;
    
    const minTimeStr = String(minHours).padStart(2, '0') + ':' + String(minMinutes).padStart(2, '0');
    const maxTimeStr = String(maxHours).padStart(2, '0') + ':' + String(maxMinutes).padStart(2, '0');
    
    minDisplay.textContent = minTimeStr;
    maxDisplay.textContent = maxTimeStr;
    
    // Обновляем подсказки на кружках
    if (minTooltip) minTooltip.textContent = minTimeStr;
    if (maxTooltip) maxTooltip.textContent = maxTimeStr;
    
    // Вычисляем проценты для позиционирования
    const minPercent = (min / 1440) * 100;
    const maxPercent = (max / 1440) * 100;
    
    // Обновляем позиции кружков
    if (minHandle) {
        minHandle.style.left = minPercent + '%';
        minHandle.style.right = 'auto';
        minHandle.style.transform = 'translate(-50%, -50%)';
    }
    if (maxHandle) {
        // Используем right для максимального кружка, чтобы он не выходил за границы
        maxHandle.style.right = (100 - maxPercent) + '%';
        maxHandle.style.left = 'auto';
        maxHandle.style.transform = 'translate(50%, -50%)';
    }
    
    // Обновляем активную область между кружками
    if (activeRange) {
        activeRange.style.left = minPercent + '%';
        activeRange.style.width = (maxPercent - minPercent) + '%';
    }
    
    if (min > 0 || max < 1440) {
        rangeDisplay.textContent = minTimeStr + ' - ' + maxTimeStr;
        clearBtn.style.display = 'block';
    } else {
        rangeDisplay.textContent = 'Весь период';
        clearBtn.style.display = 'none';
    }
    
    // Фильтруем фотографии
    document.querySelectorAll('.photo-item').forEach(item => {
        const timeMinutes = item.dataset.timeMinutes;
        if (timeMinutes !== undefined && timeMinutes !== null) {
            const photoTime = parseInt(timeMinutes);
            if (photoTime >= min && photoTime <= max) {
                item.style.display = 'block';
            } else {
                item.style.display = 'none';
            }
        } else {
            // Если у фотографии нет времени, показываем её только если фильтр не активен
            if (min === 0 && max === 1440) {
                item.style.display = 'block';
            } else {
                item.style.display = 'none';
            }
        }
    });
}

function clearTimeFilter() {
    const minSlider = document.getElementById('time-slider-min');
    const maxSlider = document.getElementById('time-slider-max');
    
    minSlider.value = 0;
    maxSlider.value = 1440;
    
    updateTimeFilter();
}

// Функции для перетаскивания кружков
function setupTimeHandles() {
    const minHandle = document.getElementById('time-handle-min');
    const maxHandle = document.getElementById('time-handle-max');
    const minSlider = document.getElementById('time-slider-min');
    const maxSlider = document.getElementById('time-slider-max');
    const timelineContainer = minHandle?.parentElement;
    
    if (!minHandle || !maxHandle || !timelineContainer) return;
    
    let isDragging = false;
    let currentHandle = null;
    let currentSlider = null;
    
    function startDrag(e, handle, slider) {
        isDragging = true;
        currentHandle = handle;
        currentSlider = slider;
        e.preventDefault();
    }
    
    function drag(e) {
        if (!isDragging || !currentHandle || !currentSlider || !timelineContainer) return;
        
        const rect = timelineContainer.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const percent = Math.max(0, Math.min(100, (x / rect.width) * 100));
        const minutes = Math.round((percent / 100) * 1440);
        
        currentSlider.value = minutes;
        updateTimeFilter();
    }
    
    function stopDrag() {
        isDragging = false;
        currentHandle = null;
        currentSlider = null;
    }
    
    // Обработчики для минимального кружка
    minHandle.addEventListener('mousedown', (e) => startDrag(e, minHandle, minSlider));
    minHandle.addEventListener('touchstart', (e) => startDrag(e.touches[0], minHandle, minSlider));
    
    // Обработчики для максимального кружка
    maxHandle.addEventListener('mousedown', (e) => startDrag(e, maxHandle, maxSlider));
    maxHandle.addEventListener('touchstart', (e) => startDrag(e.touches[0], maxHandle, maxSlider));
    
    // Глобальные обработчики для перетаскивания
    document.addEventListener('mousemove', drag);
    document.addEventListener('touchmove', (e) => drag(e.touches[0]));
    document.addEventListener('mouseup', stopDrag);
    document.addEventListener('touchend', stopDrag);
}

// Инициализация при загрузке страницы
document.addEventListener('DOMContentLoaded', function() {
    @if($hasTimeline && $minTime && $maxTime)
        // Устанавливаем начальные значения ползунков на основе реального времени
        const minTimeMinutes = {{ $minTime->hour * 60 + $minTime->minute }};
        const maxTimeMinutes = {{ $maxTime->hour * 60 + $maxTime->minute }};
        
        const minSlider = document.getElementById('time-slider-min');
        const maxSlider = document.getElementById('time-slider-max');
        
        if (minSlider && maxSlider) {
            minSlider.min = 0;
            minSlider.max = 1440;
            minSlider.value = 0;
            
            maxSlider.min = 0;
            maxSlider.max = 1440;
            maxSlider.value = 1440;
            
            updateTimeFilter();
            setupTimeHandles();
        }
    @endif
});
@endif
</script>
@endpush

