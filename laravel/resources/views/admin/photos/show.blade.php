@extends('layouts.app')

@section('title', 'Детали фотографии - Hunter-Photo.Ru')
@section('page-title', 'Детали фотографии')

@section('content')
    <div class="mb-6">
        <a href="{{ route('admin.photos.list') }}" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition-colors">
            ← Назад к списку
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Левая колонка: Изображение и основная информация -->
        <div class="space-y-6">
            <!-- Превью фотографии -->
            <div class="bg-[#1e1e1e] rounded-lg p-4">
                <h3 class="text-lg font-semibold text-white mb-4">Превью</h3>
                @if($photo->getDisplayUrl())
                    <img 
                        src="{{ $photo->getDisplayUrl() }}" 
                        alt="Photo" 
                        class="w-full rounded-lg"
                        onerror="this.src='data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'400\' height=\'300\'%3E%3Crect fill=\'%23333\' width=\'400\' height=\'300\'/%3E%3Ctext x=\'50%25\' y=\'50%25\' text-anchor=\'middle\' dy=\'.3em\' fill=\'%23999\'%3ENo image%3C/text%3E%3C/svg%3E'"
                    >
                @else
                    <div class="w-full h-64 bg-gray-800 rounded-lg flex items-center justify-center text-gray-500">
                        Изображение недоступно
                    </div>
                @endif
            </div>

            <!-- Основная информация -->
            <div class="bg-[#1e1e1e] rounded-lg p-4">
                <h3 class="text-lg font-semibold text-white mb-4">Основная информация</h3>
                <dl class="space-y-3">
                    <div>
                        <dt class="text-sm text-gray-400">ID</dt>
                        <dd class="text-sm text-white font-mono mt-1">{{ $photo->id }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-gray-400">Событие</dt>
                        <dd class="text-sm text-white mt-1">
                            @if($photo->event)
                                <a href="{{ route('admin.events.edit', $photo->event->id) }}" class="text-[#a78bfa] hover:text-[#8b5cf6]">
                                    {{ $photo->event->title }}
                                </a>
                            @else
                                <span class="text-gray-500">Не указано</span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm text-gray-400">Цена</dt>
                        <dd class="text-sm text-white mt-1">
                            {{ $photo->price ? number_format($photo->price, 2) . ' ₽' : 'Не указана' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm text-gray-400">Статус</dt>
                        <dd class="text-sm text-white mt-1">
                            <span class="px-2 py-1 bg-gray-700 rounded text-xs">
                                {{ $photo->status ?? 'pending' }}
                            </span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm text-gray-400">Дата создания</dt>
                        <dd class="text-sm text-white mt-1">
                            {{ $photo->created_at ? $photo->created_at->format('d.m.Y H:i:s') : '-' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm text-gray-400">Дата обновления</dt>
                        <dd class="text-sm text-white mt-1">
                            {{ $photo->updated_at ? $photo->updated_at->format('d.m.Y H:i:s') : '-' }}
                        </dd>
                    </div>
                </dl>
            </div>
        </div>

        <!-- Правая колонка: Детальные данные -->
        <div class="space-y-6">
            <!-- Файлы и пути -->
            <div class="bg-[#1e1e1e] rounded-lg p-4">
                <h3 class="text-lg font-semibold text-white mb-4">Файлы и пути</h3>
                <dl class="space-y-3">
                    <div>
                        <dt class="text-sm text-gray-400">Оригинальное имя</dt>
                        <dd class="text-sm text-white mt-1 break-all">{{ $photo->original_name ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-gray-400">Кастомное имя</dt>
                        <dd class="text-sm text-white mt-1 break-all">{{ $photo->custom_name ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-gray-400">Original Path</dt>
                        <dd class="text-sm text-white mt-1 break-all font-mono text-xs">{{ $photo->original_path ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-gray-400">Custom Path</dt>
                        <dd class="text-sm text-white mt-1 break-all font-mono text-xs">{{ $photo->custom_path ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-gray-400">S3 Custom URL</dt>
                        <dd class="text-sm text-white mt-1 break-all">
                            @if($photo->s3_custom_url)
                                <a href="{{ $photo->s3_custom_url }}" target="_blank" class="text-[#a78bfa] hover:text-[#8b5cf6]">
                                    {{ Str::limit($photo->s3_custom_url, 50) }}
                                </a>
                            @else
                                <span class="text-gray-500">-</span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm text-gray-400">S3 Original URL</dt>
                        <dd class="text-sm text-white mt-1 break-all">
                            @if($photo->s3_original_url)
                                <a href="{{ $photo->s3_original_url }}" target="_blank" class="text-[#a78bfa] hover:text-[#8b5cf6]">
                                    {{ Str::limit($photo->s3_original_url, 50) }}
                                </a>
                            @else
                                <span class="text-gray-500">-</span>
                            @endif
                        </dd>
                    </div>
                </dl>
            </div>

            <!-- Анализ лиц -->
            <div class="bg-[#1e1e1e] rounded-lg p-4">
                <h3 class="text-lg font-semibold text-white mb-4">Анализ лиц</h3>
                <dl class="space-y-3">
                    <div>
                        <dt class="text-sm text-gray-400">Найдено лиц</dt>
                        <dd class="text-sm text-white mt-1">
                            @if($photo->has_faces)
                                <span class="px-2 py-1 bg-green-500/20 text-green-400 rounded text-xs">Да</span>
                                @if($photo->face_encodings && count($photo->face_encodings) > 0)
                                    <span class="ml-2">({{ count($photo->face_encodings) }} лиц)</span>
                                @endif
                            @else
                                <span class="px-2 py-1 bg-gray-700 text-gray-400 rounded text-xs">Нет</span>
                            @endif
                        </dd>
                    </div>
                    @if($photo->face_encodings && count($photo->face_encodings) > 0)
                        <div>
                            <dt class="text-sm text-gray-400">Количество embeddings</dt>
                            <dd class="text-sm text-white mt-1">{{ count($photo->face_encodings) }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm text-gray-400">Размер первого embedding</dt>
                            <dd class="text-sm text-white mt-1 font-mono text-xs">
                                @if(isset($photo->face_encodings[0]))
                                    {{ is_array($photo->face_encodings[0]) ? count($photo->face_encodings[0]) . ' элементов' : 'N/A' }}
                                @else
                                    N/A
                                @endif
                            </dd>
                        </div>
                    @endif
                    @if($photo->face_bboxes && count($photo->face_bboxes) > 0)
                        <div>
                            <dt class="text-sm text-gray-400">Bounding boxes</dt>
                            <dd class="text-sm text-white mt-1">{{ count($photo->face_bboxes) }}</dd>
                        </div>
                    @endif
                </dl>
            </div>

            <!-- Номера -->
            <div class="bg-[#1e1e1e] rounded-lg p-4">
                <h3 class="text-lg font-semibold text-white mb-4">Распознанные номера</h3>
                @if($photo->numbers && count($photo->numbers) > 0)
                    <div class="space-y-2">
                        @foreach($photo->numbers as $number)
                            <div class="px-3 py-2 bg-blue-500/20 text-blue-400 rounded text-sm">
                                {{ $number }}
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-gray-400 text-sm">Номера не найдены</p>
                @endif
            </div>

            <!-- EXIF данные -->
            <div class="bg-[#1e1e1e] rounded-lg p-4">
                <h3 class="text-lg font-semibold text-white mb-4">EXIF данные</h3>
                <dl class="space-y-3">
                    <div>
                        <dt class="text-sm text-gray-400">Дата из EXIF</dt>
                        <dd class="text-sm text-white mt-1">
                            {{ $photo->created_at_exif ?? ($photo->date_exif ? $photo->date_exif->format('d.m.Y H:i:s') : '-') }}
                        </dd>
                    </div>
                    @if($photo->exif_data && count($photo->exif_data) > 0)
                        <div>
                            <dt class="text-sm text-gray-400">Дополнительные данные</dt>
                            <dd class="text-sm text-white mt-1">
                                <details class="mt-2">
                                    <summary class="cursor-pointer text-[#a78bfa] hover:text-[#8b5cf6]">Показать EXIF данные</summary>
                                    <pre class="mt-2 p-3 bg-[#121212] rounded text-xs overflow-auto max-h-64">{{ json_encode($photo->exif_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                </details>
                            </dd>
                        </div>
                    @endif
                </dl>
            </div>

            <!-- Связанные данные -->
            <div class="bg-[#1e1e1e] rounded-lg p-4">
                <h3 class="text-lg font-semibold text-white mb-4">Связанные данные</h3>
                <dl class="space-y-3">
                    <div>
                        <dt class="text-sm text-gray-400">В корзинах</dt>
                        <dd class="text-sm text-white mt-1">{{ $photo->carts->count() }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-gray-400">В заказах</dt>
                        <dd class="text-sm text-white mt-1">{{ $photo->orderItems->count() }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-gray-400">Поисков по лицу</dt>
                        <dd class="text-sm text-white mt-1">{{ $photo->faceSearches->count() }}</dd>
                    </div>
                </dl>
            </div>

            <!-- JSON данные (для разработчиков) -->
            <div class="bg-[#1e1e1e] rounded-lg p-4">
                <h3 class="text-lg font-semibold text-white mb-4">Все данные (JSON)</h3>
                <details>
                    <summary class="cursor-pointer text-[#a78bfa] hover:text-[#8b5cf6] mb-2">Показать все данные</summary>
                    <pre class="p-3 bg-[#121212] rounded text-xs overflow-auto max-h-96">{{ json_encode($photo->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                </details>
            </div>
        </div>
    </div>
@endsection

