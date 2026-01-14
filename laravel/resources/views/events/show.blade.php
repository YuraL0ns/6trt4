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
 <!-- Блок результатов поиска (создается динамически) -->
 <div id="search-results-section" class="hidden mb-6">
        <x-card>
            <h2 class="text-2xl font-bold text-white mb-4">Результаты поиска</h2>
            <div id="search-results-grid" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-4">
                <!-- Результаты будут добавлены динамически -->
            </div>
        </x-card>
    </div>
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
                        id="photo-{{ $photo->id }}"
                        class="group relative aspect-square bg-gray-800 rounded-lg overflow-hidden cursor-pointer photo-item" 
                        onclick="openPhotoModal('{{ $photo->id }}', currentFilterNumber !== null)"
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
                {{ $photos->links('vendor.pagination.default') }}
            </div>
        @else
            <x-empty-state 
                title="Фотографии не найдены" 
                description="В этом событии пока нет фотографий"
            />
        @endif
    </div>

   

    <!-- Модальное окно просмотра фотографии -->
    <x-modal id="photo-modal" title="Просмотр фотографии" size="xl">
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
let searchResultPhotoIds = null; // Массив ID фото из результатов поиска (для отдельного слайдера)
let isNavigatingSearchResults = false; // Флаг: навигация по результатам поиска или по всем фото
let isLoadingPhoto = false; // Флаг для предотвращения множественных загрузок

// Проверяем hash в URL при загрузке страницы для открытия конкретной фотографии
document.addEventListener('DOMContentLoaded', function() {
    const hash = window.location.hash;
    if (hash && hash.startsWith('#photo-')) {
        const photoId = hash.replace('#photo-', '');
        // Небольшая задержка, чтобы страница успела загрузиться
        setTimeout(function() {
            openPhotoModal(photoId);
            // Прокручиваем к элементу фотографии
            const photoElement = document.getElementById('photo-' + photoId);
            if (photoElement) {
                photoElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }, 500);
    }
});

function openPhotoModal(photoId, useSearchResults = false) {
    // Определяем, какой массив использовать для навигации
    if (useSearchResults && searchResultPhotoIds && searchResultPhotoIds.length > 0) {
        // Используем массив результатов поиска
        currentPhotoIndex = searchResultPhotoIds.indexOf(photoId);
        isNavigatingSearchResults = true;
        
        if (currentPhotoIndex === -1) {
            console.log('Photo ID not found in search results, adding temporarily:', photoId);
            searchResultPhotoIds.push(photoId);
            currentPhotoIndex = searchResultPhotoIds.length - 1;
        }
    } else {
        // Используем основной массив всех фото
        currentPhotoIndex = photoIds.indexOf(photoId);
        isNavigatingSearchResults = false;
        
        // Если фотография не найдена в основном списке (например, из похожих результатов),
        // добавляем её во временный список для навигации
        if (currentPhotoIndex === -1) {
            console.log('Photo ID not found in main list, adding temporarily:', photoId);
            photoIds.push(photoId);
            currentPhotoIndex = photoIds.length - 1;
        }
    }
    
    // Открываем модальное окно, если оно закрыто
    const modal = document.getElementById('photo-modal');
    if (modal && modal.classList.contains('hidden')) {
        modal.classList.remove('hidden');
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
            
            // Определяем активный массив для навигации
            const activePhotoIds = isNavigatingSearchResults && searchResultPhotoIds ? searchResultPhotoIds : photoIds;
            
            // Показываем/скрываем кнопки навигации
            const prevBtn = document.getElementById('prev-photo-btn');
            const nextBtn = document.getElementById('next-photo-btn');
            if (prevBtn) {
                if (currentPhotoIndex > 0 && activePhotoIds.length > 1) {
                    prevBtn.classList.remove('hidden');
                } else {
                    prevBtn.classList.add('hidden');
                }
            }
            if (nextBtn) {
                if (currentPhotoIndex < activePhotoIds.length - 1 && activePhotoIds.length > 1) {
                    nextBtn.classList.remove('hidden');
                } else {
                    nextBtn.classList.add('hidden');
                }
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
                <!-- Блок "Схожие фото" -->
                <div id="modal-similar-photos" class="mt-6 border-t border-gray-700 pt-6">
                    <h3 class="text-lg font-semibold text-white mb-4">Схожие фото</h3>
                    <div id="modal-similar-photos-grid" class="grid grid-cols-5 gap-3">
                        <div class="col-span-5 text-center py-4 text-gray-400">
                            Загрузка похожих фотографий...
                        </div>
                    </div>
                </div>
            `;
            
            // Загружаем похожие фотографии при открытии модального окна
            loadSimilarPhotosInModal(photoId, data);
            
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
    
    // Выбираем правильный массив в зависимости от контекста
    const activePhotoIds = isNavigatingSearchResults && searchResultPhotoIds ? searchResultPhotoIds : photoIds;
    
    const newIndex = currentPhotoIndex + direction;
    if (newIndex >= 0 && newIndex < activePhotoIds.length) {
        currentPhotoIndex = newIndex;
        loadPhoto(activePhotoIds[currentPhotoIndex]);
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

// Загрузка похожих фотографий в модальном окне
async function loadSimilarPhotosInModal(photoId, photoData) {
    const similarGrid = document.getElementById('modal-similar-photos-grid');
    if (!similarGrid) return;
    
    // Проверяем, есть ли данные для поиска
    const hasFaces = photoData.has_faces || false;
    const hasNumbers = photoData.numbers && photoData.numbers.length > 0;
    
    if (!hasFaces && !hasNumbers) {
        similarGrid.innerHTML = '<div class="col-span-5 text-center py-4 text-gray-400">Нет данных для поиска похожих фотографий</div>';
        return;
    }
    
    try {
        // Используем proxy_url для избежания CORS
        const photoUrl = photoData.proxy_url || photoData.url || photoData.fallback_url;
        if (!photoUrl) {
            similarGrid.innerHTML = '<div class="col-span-5 text-center py-4 text-gray-400">Не удалось получить URL фотографии</div>';
            return;
        }
        
        // Загружаем изображение через прокси
        const imageResponse = await fetch(photoUrl);
        if (!imageResponse.ok) {
            throw new Error('Не удалось загрузить изображение');
        }
        const imageBlob = await imageResponse.blob();
        
        // Определяем расширение файла на основе MIME-типа
        const contentType = imageResponse.headers.get('content-type') || 'image/jpeg';
        let fileExtension = 'jpg';
        if (contentType.includes('png')) {
            fileExtension = 'png';
        } else if (contentType.includes('webp')) {
            fileExtension = 'webp';
        }
        
        // Создаем File объект из Blob с правильным MIME-типом
        const imageFile = new File([imageBlob], `photo_${photoId}.${fileExtension}`, {
            type: contentType
        });
        
        // Создаем FormData
        const formData = new FormData();
        formData.append('photo', imageFile);
        
        // Пробуем сначала поиск по лицам, если есть, иначе по номерам
        const searchType = hasFaces ? 'face' : 'number';
        formData.append('type', searchType);
        formData.append('_token', document.querySelector('input[name="_token"]').value);
        
        const eventSlug = '{{ $event->slug ?? $event->id }}';
        const searchResponse = await fetch(`{{ route("events.find-similar", $event->slug ?? $event->id) }}`, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        
        if (!searchResponse.ok) {
            // Пытаемся получить детали ошибки
            let errorMessage = 'Ошибка при выполнении поиска';
            try {
                const errorData = await searchResponse.json();
                errorMessage = errorData.message || errorData.error || errorMessage;
                if (errorData.errors) {
                    // Если есть ошибки валидации, показываем их
                    const validationErrors = Object.values(errorData.errors).flat().join(', ');
                    errorMessage = validationErrors || errorMessage;
                }
                console.error('Ошибка поиска в модальном окне:', errorData);
            } catch (e) {
                console.error('Не удалось прочитать ответ об ошибке:', e);
            }
            throw new Error(errorMessage);
        }
        
        const searchData = await searchResponse.json();
        
        let results = [];
        if (searchData.status === 'processing' && searchData.task_id) {
            // Если задача асинхронная, опрашиваем статус
            const pollResult = await pollSearchTaskForModal(searchData.task_id);
            results = pollResult?.results || [];
        } else if (searchData.status === 'completed') {
            results = searchData.results || [];
        }
        
        // Фильтруем результаты: исключаем фотографии из корзины (БЕЗ ограничения количества)
        const cartPhotoIds = photoData.cart_photo_ids || [];
        const filteredResults = results
            .filter(r => !cartPhotoIds.includes(String(r.photo_id || r.id)));
        
        // Отображаем результаты в модальном окне
        displaySimilarPhotosInModal(filteredResults, similarGrid);
        
    } catch (error) {
        console.error('Ошибка загрузки похожих фотографий:', error);
        similarGrid.innerHTML = '<div class="col-span-5 text-center py-4 text-gray-400">Не удалось загрузить похожие фотографии</div>';
    }
}

// Отображение похожих фотографий в модальном окне
async function displaySimilarPhotosInModal(results, gridElement) {
    if (!results || results.length === 0) {
        gridElement.innerHTML = '<div class="col-span-5 text-center py-4 text-gray-400">Похожие фотографии не найдены</div>';
        return;
    }
    
    gridElement.innerHTML = '';
    const eventSlug = '{{ $event->slug ?? $event->id }}';
    
    // Загружаем данные для каждой фотографии
    const photoPromises = results.map(async (photoData) => {
        try {
            const response = await fetch(`/events/${eventSlug}/photo/${photoData.photo_id || photoData.id}`, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            if (!response.ok) return null;
            
            const photoInfo = await response.json();
            return {
                ...photoInfo,
                similarity: photoData.similarity || (1 - (photoData.distance || 0))
            };
        } catch (error) {
            console.error(`Error loading photo ${photoData.photo_id}:`, error);
            return null;
        }
    });
    
    const loadedPhotos = await Promise.all(photoPromises);
    // Фильтруем фотографии из корзины
    const validPhotos = loadedPhotos
        .filter(p => p !== null && !p.in_cart);
    
    if (validPhotos.length === 0) {
        gridElement.innerHTML = '<div class="col-span-5 text-center py-4 text-gray-400">Не удалось загрузить фотографии или все фотографии уже в корзине</div>';
        return;
    }
    
    // Создаем элементы для каждой фотографии (показываем все найденные)
    validPhotos.forEach((photo) => {
        const photoElement = document.createElement('div');
        photoElement.className = 'group relative aspect-square bg-gray-800 rounded-lg overflow-hidden cursor-pointer';
        photoElement.onclick = () => {
            // Открываем новую фотографию в том же модальном окне
            // Не закрываем модальное окно, чтобы избежать мерцания
            openPhotoModal(photo.id);
        };
        
        const similarityPercent = Math.round(photo.similarity * 100);
        const similarityColor = similarityPercent >= 80 ? 'bg-green-500' : (similarityPercent >= 60 ? 'bg-yellow-500' : 'bg-orange-500');
        
        photoElement.innerHTML = `
            <img 
                src="${photo.url || photo.fallback_url || ''}" 
                alt="Photo" 
                class="w-full h-full object-cover group-hover:opacity-75 transition-opacity"
                onerror="this.onerror=null; ${photo.fallback_url && photo.fallback_url !== photo.url ? `this.src='${photo.fallback_url}';` : "this.style.display='none'; this.nextElementSibling.style.display='flex';"}"
            >
            <div class="w-full h-full flex items-center justify-center text-gray-500 text-xs" style="display: none;">
                <p>Изображение не загружено</p>
            </div>
            <div class="absolute top-2 left-2 ${similarityColor} text-white px-2 py-1 rounded text-xs font-semibold">
                ${similarityPercent}%
            </div>
            ${photo.in_cart ? '<div class="absolute top-2 right-2 bg-[#a78bfa] text-white px-2 py-1 rounded text-xs">В корзине</div>' : ''}
        `;
        
        gridElement.appendChild(photoElement);
    });
}

// Поиск похожих фотографий по существующей фотографии
async function findSimilar(photoId, type) {
    console.log(`findSimilar called: photoId=${photoId}, type=${type}`);
    
    const eventSlug = '{{ $event->slug ?? $event->id }}';
    const messageDiv = document.getElementById('face-search-message');
    const statusDiv = document.getElementById('face-search-status');
    const progressDiv = document.getElementById('face-search-progress');
    const progressBar = document.getElementById('face-search-progress-bar');
    
    // Очищаем предыдущие результаты
    clearSearchResults();
    
    // Показываем статус поиска
    if (statusDiv) {
        statusDiv.classList.remove('hidden');
    }
    if (messageDiv) {
        messageDiv.textContent = type === 'face' ? 'Поиск похожих фотографий по лицу...' : 'Поиск похожих фотографий по номеру...';
        messageDiv.classList.remove('text-red-400', 'text-yellow-400', 'text-green-400');
        messageDiv.classList.add('text-yellow-400');
    }
    if (progressDiv) {
        progressDiv.classList.remove('hidden');
        if (progressBar) {
            progressBar.style.width = '10%';
        }
    }
    
    try {
        // Получаем URL фотографии для отправки
        const photoResponse = await fetch(`/events/${eventSlug}/photo/${photoId}`, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        
        if (!photoResponse.ok) {
            throw new Error('Не удалось загрузить фотографию');
        }
        
        const photoData = await photoResponse.json();
        // Используем proxy_url для избежания проблем с CORS
        const photoUrl = photoData.proxy_url || photoData.url || photoData.fallback_url;
        
        if (!photoUrl) {
            throw new Error('Не удалось получить URL фотографии');
        }
        
        // Загружаем изображение как Blob через прокси (избегаем CORS)
        const imageResponse = await fetch(photoUrl);
        if (!imageResponse.ok) {
            throw new Error('Не удалось загрузить изображение');
        }
        const imageBlob = await imageResponse.blob();
        
        // Определяем расширение файла на основе MIME-типа
        const contentType = imageResponse.headers.get('content-type') || 'image/jpeg';
        let fileExtension = 'jpg';
        if (contentType.includes('png')) {
            fileExtension = 'png';
        } else if (contentType.includes('webp')) {
            fileExtension = 'webp';
        }
        
        // Создаем File объект из Blob с правильным MIME-типом
        const imageFile = new File([imageBlob], `photo_${photoId}.${fileExtension}`, {
            type: contentType
        });
        
        // Создаем FormData для отправки
        const formData = new FormData();
        formData.append('photo', imageFile);
        formData.append('type', type);
        formData.append('_token', document.querySelector('input[name="_token"]').value);
        
        if (progressBar) {
            progressBar.style.width = '30%';
        }
        
        // Отправляем запрос на поиск
        const searchResponse = await fetch(`{{ route("events.find-similar", $event->slug ?? $event->id) }}`, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        
        if (!searchResponse.ok) {
            const errorData = await searchResponse.json().catch(() => ({ error: 'Ошибка при выполнении поиска' }));
            throw new Error(errorData.error || 'Ошибка при выполнении поиска');
        }
        
        const searchData = await searchResponse.json();
        
        if (progressBar) {
            progressBar.style.width = '50%';
        }
        
        let finalResults = [];
        if (searchData.status === 'processing' && searchData.task_id) {
            // Задача запущена асинхронно, нужно опрашивать статус
            if (messageDiv) {
                messageDiv.textContent = 'Поиск выполняется, пожалуйста подождите...';
            }
            
            const pollResult = await pollSearchTask(searchData.task_id);
            finalResults = pollResult?.results || [];
        } else if (searchData.status === 'completed') {
            // Результаты готовы сразу
            finalResults = searchData.results || [];
            await showSearchResults(finalResults, searchData.total || 0);
        } else {
            throw new Error('Неизвестный статус ответа');
        }
    } catch (error) {
        console.error('Ошибка поиска:', error);
        if (messageDiv) {
            messageDiv.textContent = 'Ошибка: ' + error.message;
            messageDiv.classList.remove('text-yellow-400', 'text-green-400');
            messageDiv.classList.add('text-red-400');
        }
        if (progressDiv) {
            progressDiv.classList.add('hidden');
        }
    }
}

// Очистка результатов поиска при новом поиске
function clearSearchResults() {
    const searchResultsSection = document.getElementById('search-results-section');
    if (searchResultsSection) {
        searchResultsSection.classList.add('hidden');
    }
    // Сбрасываем подсветку
    document.querySelectorAll('.photo-item').forEach(item => {
        item.classList.remove('ring-4', 'ring-[#a78bfa]');
    });
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
            
            // Очищаем предыдущие результаты
            clearSearchResults();
            
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
                    await showSearchResults(data.results || [], data.total || 0);
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

// Опрос статуса асинхронной задачи (для использования в loadSimilarPhotosInModal)
async function pollSearchTaskForModal(taskId) {
    const maxAttempts = 60;
    let attempts = 0;
    
    const poll = async () => {
        try {
            const response = await fetch(`/api/search-task/${taskId}/status`);
            const data = await response.json();
            
            if (data.status === 'completed') {
                let results = [];
                let total = 0;
                
                if (data.results && Array.isArray(data.results)) {
                    results = data.results;
                    total = data.total || data.results.length;
                } else if (data.result && data.result.results && Array.isArray(data.result.results)) {
                    results = data.result.results;
                    total = data.result.total_found || data.result.total || data.result.results.length;
                } else if (data.result && Array.isArray(data.result)) {
                    results = data.result;
                    total = data.total || data.result.length;
                }
                
                return { results, total };
            } else if (data.status === 'failed') {
                throw new Error(data.error || 'Задача завершилась с ошибкой');
            } else {
                attempts++;
                if (attempts < maxAttempts) {
                    await new Promise(resolve => setTimeout(resolve, 5000));
                    return poll();
                } else {
                    throw new Error('Превышено время ожидания задачи');
                }
            }
        } catch (error) {
            console.error('Ошибка при опросе задачи:', error);
            throw error;
        }
    };
    
    return poll();
}

// Опрос статуса асинхронной задачи (для основного поиска)
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
                
                // КРИТИЧЕСКОЕ ИСПРАВЛЕНИЕ: Правильно извлекаем результаты из ответа
                // Результаты могут быть в data.results (новый формат) или data.result.results (старый формат)
                let results = [];
                let total = 0;
                
                console.log('Search task completed, data:', data);
                
                if (data.results && Array.isArray(data.results)) {
                    // Новый формат: результаты напрямую в data.results
                    results = data.results;
                    total = data.total || data.results.length;
                    console.log('Using new format: results from data.results, total:', total);
                } else if (data.result && data.result.results && Array.isArray(data.result.results)) {
                    // Старый формат: результаты в data.result.results
                    results = data.result.results;
                    total = data.result.total_found || data.result.total || data.result.results.length;
                    console.log('Using old format: results from data.result.results, total:', total);
                } else if (data.result && Array.isArray(data.result)) {
                    // Альтернативный формат: результат - это массив
                    results = data.result;
                    total = data.total || data.result.length;
                    console.log('Using alternative format: results from data.result array, total:', total);
                } else {
                    console.warn('No results found in data:', data);
                }
                
                console.log('Final results:', results, 'total:', total);
                await showSearchResults(results, total);
                return { results, total }; // Возвращаем результаты для использования в loadSimilarPhotosInModal
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
async function showSearchResults(results, total) {
    console.log('showSearchResults called with:', { results, total, resultsLength: results ? results.length : 0 });
    
    const messageDiv = document.getElementById('face-search-message');
    const progressDiv = document.getElementById('face-search-progress');
    const progressBar = document.getElementById('face-search-progress-bar');
    const searchResultsSection = document.getElementById('search-results-section');
    const searchResultsGrid = document.getElementById('search-results-grid');
    
    progressBar.style.width = '100%';
    progressDiv.classList.add('hidden');
    
    if (!results || !Array.isArray(results) || results.length === 0 || total === 0) {
        messageDiv.textContent = 'Похожих фотографий не найдено';
        messageDiv.classList.remove('text-red-400', 'text-green-400');
        messageDiv.classList.add('text-yellow-400');
        // Сбрасываем подсветку
        document.querySelectorAll('.photo-item').forEach(item => {
            item.classList.remove('ring-4', 'ring-[#a78bfa]');
        });
        // Скрываем блок результатов
        if (searchResultsSection) {
            searchResultsSection.classList.add('hidden');
        }
        console.log('No results to display');
        return;
    }
    
    // Получаем список фотографий в корзине (из первого результата или запрашиваем отдельно)
    let cartPhotoIds = [];
    try {
        const eventSlug = '{{ $event->slug ?? $event->id }}';
        const firstPhotoId = results[0]?.photo_id || results[0]?.id;
        if (firstPhotoId) {
            const photoResponse = await fetch(`/events/${eventSlug}/photo/${firstPhotoId}`, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            if (photoResponse.ok) {
                const photoData = await photoResponse.json();
                cartPhotoIds = photoData.cart_photo_ids || [];
            }
        }
    } catch (error) {
        console.error('Error fetching cart photo IDs:', error);
    }
    
    // Фильтруем результаты: исключаем фотографии из корзины (БЕЗ ограничения количества)
    const filteredResults = results
        .filter(r => !cartPhotoIds.includes(String(r.photo_id || r.id)));
    
    const filteredTotal = filteredResults.length;
    const excludedFromCart = total - filteredTotal;
    
    messageDiv.textContent = `Найдено похожих фотографий: ${filteredTotal}${excludedFromCart > 0 ? ` (из ${total}, исключены ${excludedFromCart} фотографий из корзины)` : (filteredTotal < total ? ` (из ${total})` : '')}`;
    messageDiv.classList.remove('text-red-400', 'text-yellow-400');
    messageDiv.classList.add('text-green-400');
    
    // Получаем ID найденных фотографий (сортируем по similarity/distance) из отфильтрованных результатов
    const foundPhotos = filteredResults.map(r => ({
        id: String(r.photo_id || r.id || ''),
        distance: r.distance || 0,
        similarity: r.similarity || (1 - (r.distance || 0))
    })).filter(p => p.id).sort((a, b) => a.distance - b.distance); // Сортируем по distance (меньше = лучше)
    
    const foundPhotoIds = foundPhotos.map(p => p.id);
    
    console.log('Found photo IDs:', foundPhotoIds);
    console.log('Total photo items on page:', document.querySelectorAll('.photo-item').length);
    
    // Подсвечиваем найденные фотографии в основной галерее
    let firstFound = false;
    let highlightedCount = 0;
    document.querySelectorAll('.photo-item').forEach(item => {
        const photoId = String(item.dataset.photoId || '');
        if (foundPhotoIds.includes(photoId)) {
            item.classList.add('ring-4', 'ring-[#a78bfa]');
            highlightedCount++;
            console.log('Highlighted photo:', photoId);
            // Прокручиваем к первой найденной фотографии
            if (!firstFound) {
                item.scrollIntoView({ behavior: 'smooth', block: 'center' });
                firstFound = true;
            }
        } else {
            item.classList.remove('ring-4', 'ring-[#a78bfa]');
        }
    });
    
    console.log(`Highlighted ${highlightedCount} photos out of ${foundPhotoIds.length} found`);
    
    // КРИТИЧЕСКОЕ ИСПРАВЛЕНИЕ: Создаем блок "Результаты поиска" с найденными фотографиями
    if (searchResultsSection && searchResultsGrid) {
        // Показываем блок результатов
        searchResultsSection.classList.remove('hidden');
        searchResultsGrid.innerHTML = '<div class="col-span-full text-center py-4"><div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-[#a78bfa]"></div><p class="text-gray-400 mt-2">Загрузка результатов...</p></div>';
        
        // Загружаем данные для каждой найденной фотографии
        const eventSlug = '{{ $event->slug ?? $event->id }}';
        const photoPromises = foundPhotos.map(async (photoData, index) => {
            try {
                const response = await fetch(`/events/${eventSlug}/photo/${photoData.id}`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                
                if (!response.ok) {
                    console.error(`Failed to load photo ${photoData.id}: ${response.status}`);
                    return null;
                }
                
                const photoInfo = await response.json();
                return {
                    ...photoInfo,
                    distance: photoData.distance,
                    similarity: photoData.similarity
                };
            } catch (error) {
                console.error(`Error loading photo ${photoData.id}:`, error);
                return null;
            }
        });
        
        // Ждем загрузки всех фотографий
        const loadedPhotos = await Promise.all(photoPromises);
        // Фильтруем фотографии из корзины
        const validPhotos = loadedPhotos
            .filter(p => p !== null && !p.in_cart);
        
        console.log(`Loaded ${validPhotos.length} photos out of ${foundPhotos.length} (excluding ${loadedPhotos.filter(p => p && p.in_cart).length} in cart)`);
        
        // Очищаем grid и добавляем фотографии
        searchResultsGrid.innerHTML = '';
        
        if (validPhotos.length === 0) {
            searchResultsGrid.innerHTML = '<div class="col-span-full text-center py-8 text-gray-400">Не удалось загрузить фотографии или все фотографии уже в корзине</div>';
            return;
        }
        
        // Сохраняем массив ID найденных фото для навигации в модальном окне
        searchResultPhotoIds = validPhotos.map(p => p.id);
        console.log('Search results photo IDs saved for navigation:', searchResultPhotoIds);
        
        // Создаем элементы для каждой фотографии (показываем все найденные)
        validPhotos.forEach((photo, index) => {
            const photoElement = document.createElement('div');
            photoElement.className = 'group relative aspect-square bg-gray-800 rounded-lg overflow-hidden cursor-pointer';
            photoElement.onclick = () => openPhotoModal(photo.id, true); // Передаем true для использования результатов поиска
            
            const similarityPercent = Math.round(photo.similarity * 100);
            const similarityColor = similarityPercent >= 80 ? 'bg-green-500' : (similarityPercent >= 60 ? 'bg-yellow-500' : 'bg-orange-500');
            
            photoElement.innerHTML = `
                <img 
                    src="${photo.url || photo.fallback_url || ''}" 
                    alt="Photo" 
                    class="w-full h-full object-cover group-hover:opacity-75 transition-opacity"
                    onerror="this.onerror=null; ${photo.fallback_url && photo.fallback_url !== photo.url ? `this.src='${photo.fallback_url}';` : "this.style.display='none'; this.nextElementSibling.style.display='flex';"}"
                >
                <div class="w-full h-full flex items-center justify-center text-gray-500 text-xs" style="display: none;">
                    <p>Изображение не загружено</p>
                </div>
                <div class="absolute top-2 left-2 ${similarityColor} text-white px-2 py-1 rounded text-xs font-semibold">
                    ${similarityPercent}%
                </div>
                <div class="absolute inset-0 bg-black/0 group-hover:bg-black/50 transition-all flex items-center justify-center">
                    <div class="opacity-0 group-hover:opacity-100 transition-opacity text-white text-center">
                        <p class="font-semibold">${photo.price || '0'} ₽</p>
                    </div>
                </div>
            `;
            
            searchResultsGrid.appendChild(photoElement);
        });
        
        // Прокручиваем к блоку результатов
        searchResultsSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
    } else {
        console.warn('Search results section elements not found!');
    }
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
    
    // Сохраняем массив отфильтрованных фото для навигации
    const filteredPhotoIds = [];
    
    // Фильтруем фотографии
    document.querySelectorAll('.photo-item').forEach(item => {
        const numbers = JSON.parse(item.dataset.numbers || '[]');
        const hasNumber = numbers.includes(number);
        
        if (hasNumber) {
            item.style.display = 'block';
            const photoId = item.dataset.photoId;
            if (photoId) {
                filteredPhotoIds.push(photoId);
            }
        } else {
            item.style.display = 'none';
        }
    });
    
    // Сохраняем массив для навигации
    searchResultPhotoIds = filteredPhotoIds;
    console.log('Filtered photo IDs saved for navigation:', searchResultPhotoIds);
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
    
    // Сбрасываем массив результатов поиска
    searchResultPhotoIds = null;
    isNavigatingSearchResults = false;
    
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

