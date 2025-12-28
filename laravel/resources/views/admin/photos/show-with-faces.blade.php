@extends('layouts.app')

@section('title', 'Просмотр фото с лицами - Админ-панель')
@section('page-title', 'Просмотр фото с выделенными областями лиц')

@section('content')
    <div class="space-y-6">
        <x-card>
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="text-xl font-bold text-white">{{ $photo->event->title ?? 'Без события' }}</h2>
                    <p class="text-sm text-gray-400 mt-1">{{ $photo->created_at->format('d.m.Y H:i') }}</p>
                </div>
                <a href="{{ route('admin.photos.index') }}" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition-colors">
                    Назад к списку
                </a>
            </div>
            
            @php
                $photoUrl = $photo->getDisplayUrl();
                $faceBboxes = $photo->face_bboxes ?? [];
                // Убеждаемся, что face_bboxes это массив
                if (!is_array($faceBboxes)) {
                    $faceBboxes = [];
                }
            @endphp
            
            @if($photoUrl)
                <div class="relative bg-gray-900 rounded-lg overflow-hidden" id="photo-container">
                    <img 
                        id="photo-image" 
                        src="{{ $photoUrl }}" 
                        alt="Photo" 
                        class="w-full h-auto"
                        onload="drawFaceBoxes()"
                    >
                    <canvas id="face-canvas" class="absolute top-0 left-0 pointer-events-none" style="width: 100%; height: 100%;"></canvas>
                </div>
                
                <div class="mt-4 p-4 bg-gray-800 rounded-lg">
                    <h3 class="text-lg font-semibold text-white mb-2">Информация о фотографии</h3>
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <span class="text-gray-400">Найдено лиц:</span>
                            <span class="text-white ml-2 font-semibold">{{ count($faceBboxes) }}</span>
                        </div>
                        <div>
                            <span class="text-gray-400">Цена:</span>
                            <span class="text-white ml-2 font-semibold">{{ number_format($photo->price, 2) }} ₽</span>
                        </div>
                        <div>
                            <span class="text-gray-400">Статус:</span>
                            <span class="text-white ml-2 font-semibold">
                                @if($photo->status === 'done')
                                    <span class="text-green-400">Обработано</span>
                                @elseif($photo->status === 'processing')
                                    <span class="text-yellow-400">Обработка</span>
                                @elseif($photo->status === 'error')
                                    <span class="text-red-400">Ошибка</span>
                                @else
                                    <span class="text-gray-400">Ожидание</span>
                                @endif
                            </span>
                        </div>
                        @if($photo->has_faces)
                            <div>
                                <span class="text-gray-400">Есть лица:</span>
                                <span class="text-green-400 ml-2 font-semibold">Да</span>
                            </div>
                        @endif
                    </div>
                </div>
            @else
                <x-empty-state 
                    title="Фото не найдено" 
                    description="Не удалось загрузить изображение"
                />
            @endif
        </x-card>
    </div>

    <script>
        const faceBboxes = @json($faceBboxes);
        
        function drawFaceBoxes() {
            const img = document.getElementById('photo-image');
            const canvas = document.getElementById('face-canvas');
            const container = document.getElementById('photo-container');
            
            if (!img || !canvas || !faceBboxes || faceBboxes.length === 0) {
                return;
            }
            
            // Ждем загрузки изображения
            if (!img.complete) {
                img.onload = drawFaceBoxes;
                return;
            }
            
            // Устанавливаем размеры canvas равными размерам изображения
            const rect = container.getBoundingClientRect();
            const imgWidth = img.naturalWidth;
            const imgHeight = img.naturalHeight;
            const displayWidth = img.clientWidth;
            const displayHeight = img.clientHeight;
            
            canvas.width = displayWidth;
            canvas.height = displayHeight;
            
            const ctx = canvas.getContext('2d');
            ctx.strokeStyle = '#a78bfa';
            ctx.lineWidth = 3;
            ctx.font = '14px Arial';
            ctx.fillStyle = '#a78bfa';
            
            // Масштабируем координаты bbox под размер отображаемого изображения
            const scaleX = displayWidth / imgWidth;
            const scaleY = displayHeight / imgHeight;
            
            faceBboxes.forEach((bbox, index) => {
                if (!bbox || bbox.length < 4) return;
                
                // bbox: [x1, y1, x2, y2]
                const x1 = bbox[0] * scaleX;
                const y1 = bbox[1] * scaleY;
                const x2 = bbox[2] * scaleX;
                const y2 = bbox[3] * scaleY;
                
                // Рисуем прямоугольник
                ctx.strokeRect(x1, y1, x2 - x1, y2 - y1);
                
                // Рисуем номер лица
                ctx.fillStyle = '#a78bfa';
                ctx.fillRect(x1, y1 - 20, 30, 20);
                ctx.fillStyle = '#ffffff';
                ctx.fillText((index + 1).toString(), x1 + 8, y1 - 5);
                ctx.fillStyle = '#a78bfa';
            });
        }
        
        // Перерисовываем при изменении размера окна
        window.addEventListener('resize', drawFaceBoxes);
    </script>
@endsection

