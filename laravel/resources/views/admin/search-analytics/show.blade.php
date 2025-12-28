@extends('layouts.app')

@section('title', 'Детали поиска - Hunter-Photo.Ru')
@section('page-title', 'Детали поиска')

@section('content')
    <x-card>
        <div class="space-y-6">
            <!-- Информация о поиске -->
            <div>
                <h3 class="text-lg font-semibold text-white mb-4">Информация о поиске</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <p class="text-sm text-gray-400 mb-1">Дата поиска</p>
                        <p class="text-white">{{ $search->created_at->format('d.m.Y H:i:s') }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-400 mb-1">Оценка схожести</p>
                        <p class="text-white font-semibold text-lg">
                            <span class="px-2 py-1 rounded 
                                @if($search->similarity_score >= 80) bg-green-900/50 text-green-400
                                @elseif($search->similarity_score >= 60) bg-yellow-900/50 text-yellow-400
                                @else bg-red-900/50 text-red-400
                                @endif">
                                {{ number_format($search->similarity_score, 2, ',', ' ') }}%
                            </span>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Найденная фотография -->
            @if($search->photo)
                <div>
                    <h3 class="text-lg font-semibold text-white mb-4">Найденная фотография</h3>
                    <div class="bg-[#121212] rounded-lg p-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <p class="text-sm text-gray-400 mb-1">ID фотографии</p>
                                <a href="{{ route('admin.photos.show', $search->photo->id) }}" class="text-[#a78bfa] hover:text-[#8b5cf6]">
                                    {{ $search->photo->id }}
                                </a>
                            </div>
                            <div>
                                <p class="text-sm text-gray-400 mb-1">Имя файла</p>
                                <p class="text-white">{{ $search->photo->original_name ?? 'Не указано' }}</p>
                            </div>
                            @if($search->photo->event)
                                <div>
                                    <p class="text-sm text-gray-400 mb-1">Событие</p>
                                    <a href="{{ route('admin.events.show', $search->photo->event->slug) }}" class="text-[#a78bfa] hover:text-[#8b5cf6]">
                                        {{ $search->photo->event->title }}
                                    </a>
                                </div>
                            @endif
                            <div>
                                <p class="text-sm text-gray-400 mb-1">Наличие лиц</p>
                                <p class="text-white">
                                    @if($search->photo->has_faces)
                                        <span class="text-green-400">Да</span>
                                    @else
                                        <span class="text-red-400">Нет</span>
                                    @endif
                                </p>
                            </div>
                        </div>
                        @if($search->photo->getDisplayUrl())
                            <div class="mt-4">
                                <img src="{{ $search->photo->getDisplayUrl() }}" alt="Фотография" class="max-w-full h-auto rounded-lg" style="max-height: 400px;">
                            </div>
                        @endif
                    </div>
                </div>
            @else
                <div class="bg-red-900/20 border border-red-700 rounded-lg p-4">
                    <p class="text-red-400">Фотография была удалена</p>
                </div>
            @endif

            <!-- Загруженное фото пользователя -->
            <div>
                <h3 class="text-lg font-semibold text-white mb-4">Загруженное фото пользователя</h3>
                <div class="bg-[#121212] rounded-lg p-4">
                    <p class="text-sm text-gray-400 mb-2">Путь к файлу:</p>
                    <p class="text-white font-mono text-sm break-all">{{ $search->user_uploaded_photo_path }}</p>
                    @php
                        $photoPath = storage_path('app/public/' . $search->user_uploaded_photo_path);
                        $photoUrl = asset('storage/' . $search->user_uploaded_photo_path);
                    @endphp
                    @if(file_exists($photoPath))
                        <div class="mt-4">
                            <img src="{{ $photoUrl }}" alt="Загруженное фото" class="max-w-full h-auto rounded-lg" style="max-height: 400px;">
                        </div>
                    @else
                        <p class="text-gray-500 mt-2">Файл не найден</p>
                    @endif
                </div>
            </div>

            <!-- Действия -->
            <div class="flex space-x-4">
                <a href="{{ route('admin.search-analytics.index') }}" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition-colors">
                    Назад к списку
                </a>
                @if($search->photo)
                    <a href="{{ route('admin.photos.show', $search->photo->id) }}" class="px-4 py-2 bg-[#a78bfa] hover:bg-[#8b5cf6] text-white rounded-lg transition-colors">
                        Просмотр фотографии
                    </a>
                @endif
            </div>
        </div>
    </x-card>
@endsection

