@extends('layouts.app')

@section('title', 'Фотографии с лицами - Админ-панель')
@section('page-title', 'Фотографии с лицами')

@section('content')
    <div class="space-y-6">
        <!-- Фильтры -->
        <x-card>
            <form method="GET" action="{{ route('admin.photos.index') }}" class="flex gap-4 items-end">
                <div class="flex-1">
                    <label class="block text-sm font-medium text-gray-300 mb-2">Событие</label>
                    <select name="event_id" class="w-full px-4 py-2 bg-[#121212] border border-gray-700 rounded-lg text-white">
                        <option value="">Все события</option>
                        @foreach($events as $event)
                            <option value="{{ $event->id }}" {{ request('event_id') == $event->id ? 'selected' : '' }}>
                                {{ $event->title }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <x-button type="submit">Применить фильтр</x-button>
                @if(request('event_id'))
                    <a href="{{ route('admin.photos.index') }}" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition-colors">
                        Сбросить
                    </a>
                @endif
            </form>
        </x-card>

        <!-- Список фотографий -->
        <x-card>
            <h2 class="text-xl font-bold text-white mb-4">Найдено фотографий: {{ $photos->total() }}</h2>
            
            @if($photos->count() > 0)
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                    @foreach($photos as $photo)
                        <div class="bg-gray-800 rounded-lg overflow-hidden">
                            <a href="{{ route('admin.photos.show-with-faces', $photo->id) }}" class="block">
                                <div class="aspect-square bg-gray-700 relative overflow-hidden">
                                    @php
                                        $photoUrl = $photo->getDisplayUrl();
                                    @endphp
                                    @if($photoUrl)
                                        <img src="{{ $photoUrl }}" alt="Photo" class="w-full h-full object-cover">
                                    @else
                                        <div class="w-full h-full flex items-center justify-center text-gray-400">
                                            Фото не найдено
                                        </div>
                                    @endif
                                    @if($photo->face_bboxes && is_array($photo->face_bboxes) && count($photo->face_bboxes) > 0)
                                        <div class="absolute top-2 right-2 bg-[#a78bfa] text-white px-2 py-1 rounded text-xs font-semibold">
                                            {{ count($photo->face_bboxes) }} {{ count($photo->face_bboxes) == 1 ? 'лицо' : 'лиц' }}
                                        </div>
                                    @endif
                                </div>
                                <div class="p-3">
                                    <p class="text-sm text-gray-400 truncate">{{ $photo->event?->title ?? 'Без события' }}</p>
                                    <p class="text-xs text-gray-500 mt-1">{{ $photo->created_at?->format('d.m.Y H:i') ?? '' }}</p>
                                </div>
                            </a>
                        </div>
                    @endforeach
                </div>
                
                <div class="mt-6">
                    {{ $photos->links('vendor.pagination.default') }}
                </div>
            @else
                <x-empty-state 
                    title="Фотографии не найдены" 
                    description="Нет фотографий с обнаруженными лицами"
                />
            @endif
        </x-card>
    </div>
@endsection

