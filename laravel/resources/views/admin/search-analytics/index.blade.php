@extends('layouts.app')

@section('title', 'Анализы поиска - Hunter-Photo.Ru')
@section('page-title', 'Анализы поиска')

@section('content')
    <!-- Статистика -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6 mb-8">
        <x-card>
            <p class="text-sm text-gray-400 mb-1">Всего поисков</p>
            <p class="text-3xl font-bold text-white">{{ number_format($stats['total_searches'], 0, ',', ' ') }}</p>
        </x-card>
        
        <x-card>
            <p class="text-sm text-gray-400 mb-1">Уникальных фото найдено</p>
            <p class="text-3xl font-bold text-[#a78bfa]">{{ number_format($stats['unique_photos_found'], 0, ',', ' ') }}</p>
        </x-card>
        
        <x-card>
            <p class="text-sm text-gray-400 mb-1">Средняя схожесть</p>
            <p class="text-3xl font-bold text-white">{{ number_format($stats['avg_similarity'], 2, ',', ' ') }}%</p>
        </x-card>
        
        <x-card>
            <p class="text-sm text-gray-400 mb-1">Максимальная схожесть</p>
            <p class="text-3xl font-bold text-[#a78bfa]">{{ number_format($stats['max_similarity'], 2, ',', ' ') }}%</p>
        </x-card>
        
        <x-card>
            <p class="text-sm text-gray-400 mb-1">Минимальная схожесть</p>
            <p class="text-3xl font-bold text-white">{{ number_format($stats['min_similarity'], 2, ',', ' ') }}%</p>
        </x-card>
    </div>

    <!-- Фильтры -->
    <x-card class="mb-6">
        <form action="{{ route('admin.search-analytics.index') }}" method="GET" class="flex items-end space-x-4">
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
            <div class="flex-1">
                <label class="block text-sm font-medium text-gray-300 mb-2">Мин. схожесть (%)</label>
                <input type="number" name="min_similarity" value="{{ request('min_similarity') }}" min="0" max="100" step="0.01" class="w-full px-4 py-2 bg-[#121212] border border-gray-700 rounded-lg text-white">
            </div>
            <x-button type="submit">Применить</x-button>
            @if(request()->hasAny(['event_id', 'min_similarity']))
                <a href="{{ route('admin.search-analytics.index') }}" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition-colors">
                    Сбросить
                </a>
            @endif
        </form>
    </x-card>

    <!-- Топ фотографий -->
    @if($topPhotos->count() > 0)
        <x-card title="Топ фотографий по количеству найденных совпадений" class="mb-6">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-700">
                            <th class="text-left py-3 px-4 text-sm font-semibold text-gray-400">Фото</th>
                            <th class="text-left py-3 px-4 text-sm font-semibold text-gray-400">Событие</th>
                            <th class="text-center py-3 px-4 text-sm font-semibold text-gray-400">Кол-во поисков</th>
                            <th class="text-center py-3 px-4 text-sm font-semibold text-gray-400">Средняя схожесть</th>
                            <th class="text-right py-3 px-4 text-sm font-semibold text-gray-400">Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($topPhotos as $item)
                            <tr class="border-b border-gray-800 hover:bg-gray-800/50">
                                <td class="py-3 px-4">
                                    @if($item->photo)
                                        <a href="{{ route('admin.photos.show', $item->photo->id) }}" class="text-[#a78bfa] hover:text-[#8b5cf6]">
                                            {{ $item->photo->original_name ?? $item->photo->id }}
                                        </a>
                                    @else
                                        <span class="text-gray-500">Удалено</span>
                                    @endif
                                </td>
                                <td class="py-3 px-4 text-gray-300">
                                    @if($item->photo && $item->photo->event)
                                        {{ $item->photo->event->title }}
                                    @else
                                        <span class="text-gray-500">-</span>
                                    @endif
                                </td>
                                <td class="py-3 px-4 text-center text-white font-semibold">
                                    {{ number_format($item->search_count, 0, ',', ' ') }}
                                </td>
                                <td class="py-3 px-4 text-center text-[#a78bfa] font-semibold">
                                    {{ number_format($item->avg_similarity, 2, ',', ' ') }}%
                                </td>
                                <td class="py-3 px-4 text-right">
                                    @if($item->photo)
                                        <a href="{{ route('admin.photos.show', $item->photo->id) }}" class="text-[#a78bfa] hover:text-[#8b5cf6] text-sm">
                                            Просмотр
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>
    @endif

    <!-- История поисков -->
    <x-card title="История поисков">
        @if($searches->count() > 0)
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-700">
                            <th class="text-left py-3 px-4 text-sm font-semibold text-gray-400">Дата</th>
                            <th class="text-left py-3 px-4 text-sm font-semibold text-gray-400">Фото</th>
                            <th class="text-left py-3 px-4 text-sm font-semibold text-gray-400">Событие</th>
                            <th class="text-center py-3 px-4 text-sm font-semibold text-gray-400">Схожесть</th>
                            <th class="text-right py-3 px-4 text-sm font-semibold text-gray-400">Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($searches as $search)
                            <tr class="border-b border-gray-800 hover:bg-gray-800/50">
                                <td class="py-3 px-4 text-gray-300">
                                    {{ $search->created_at->format('d.m.Y H:i') }}
                                </td>
                                <td class="py-3 px-4">
                                    @if($search->photo)
                                        <a href="{{ route('admin.photos.show', $search->photo->id) }}" class="text-[#a78bfa] hover:text-[#8b5cf6]">
                                            {{ $search->photo->original_name ?? $search->photo->id }}
                                        </a>
                                    @else
                                        <span class="text-gray-500">Удалено</span>
                                    @endif
                                </td>
                                <td class="py-3 px-4 text-gray-300">
                                    @if($search->photo && $search->photo->event)
                                        <a href="{{ route('admin.events.show', $search->photo->event->slug) }}" class="text-[#a78bfa] hover:text-[#8b5cf6]">
                                            {{ $search->photo->event->title }}
                                        </a>
                                    @else
                                        <span class="text-gray-500">-</span>
                                    @endif
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <span class="px-2 py-1 rounded text-sm font-semibold 
                                        @if($search->similarity_score >= 80) bg-green-900/50 text-green-400
                                        @elseif($search->similarity_score >= 60) bg-yellow-900/50 text-yellow-400
                                        @else bg-red-900/50 text-red-400
                                        @endif">
                                        {{ number_format($search->similarity_score, 2, ',', ' ') }}%
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-right">
                                    <a href="{{ route('admin.search-analytics.show', $search->id) }}" class="text-[#a78bfa] hover:text-[#8b5cf6] text-sm">
                                        Детали
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            
            <div class="mt-6">
                {{ $searches->links('vendor.pagination.default') }}
            </div>
        @else
            <p class="text-gray-400 text-center py-8">Поисков не найдено</p>
        @endif
    </x-card>
@endsection

