@extends('layouts.app')

@section('title', 'События - Hunter-Photo.Ru')
@section('page-title', 'События')
@section('page-description', 'Все спортивные события')

@section('content')
    <!-- Фильтры и поиск -->
    <div class="mb-6">
        <x-card>
            <form method="GET" action="{{ route('events.index') }}" class="flex flex-col md:flex-row gap-4">
                <div class="flex-1">
                    <input 
                        type="text" 
                        name="search" 
                        value="{{ request('search') }}"
                        placeholder="Поиск по названию или городу..." 
                        class="w-full px-4 py-2 bg-[#1a1a1a] border border-gray-700 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-[#a78bfa] focus:border-transparent"
                    >
                </div>
                <div>
                    <select name="sort" class="px-4 py-2 bg-[#1a1a1a] border border-gray-700 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-[#a78bfa] focus:border-transparent">
                        <option value="newest" {{ request('sort') === 'newest' ? 'selected' : '' }}>Новые сначала</option>
                        <option value="oldest" {{ request('sort') === 'oldest' ? 'selected' : '' }}>Старые сначала</option>
                    </select>
                </div>
                <x-button type="submit">Применить</x-button>
                @if(request('search') || request('sort'))
                    <x-button href="{{ route('events.index') }}" variant="outline" type="button">Сбросить</x-button>
                @endif
            </form>
        </x-card>
    </div>

    @if($events->count() > 0)
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
            @foreach($events as $event)
                <x-card>
                    @if($event->cover_path)
                        <div class="aspect-video bg-gray-800 rounded-lg mb-4 overflow-hidden">
                            <img src="{{ Storage::url($event->cover_path) }}" alt="{{ $event->title }}" class="w-full h-full object-cover">
                        </div>
                    @else
                        <div class="aspect-video bg-gray-800 rounded-lg mb-4 flex items-center justify-center">
                            <svg class="w-16 h-16 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                        </div>
                    @endif
                    
                    <div class="mb-2" style="height: calc(1.125rem * 2 + 1.5rem); overflow: hidden;">
                        <h3 class="text-lg font-semibold text-white line-clamp-2" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; text-overflow: ellipsis; line-height: 1.5rem;">{{ $event->title }}</h3>
                    </div>
                    <p class="text-sm text-gray-400 mb-1">
                        <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                        {{ $event->city }}
                    </p>
                    <p class="text-sm text-gray-400 mb-4">
                        <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                        {{ $event->getFormattedDate() }}
                    </p>
                    <x-button href="{{ route('events.show', $event->slug ?? $event->id) }}" size="sm" class="w-full">
                        Просмотреть
                    </x-button>
                </x-card>
            @endforeach
        </div>

        <div class="mt-6">
            {{ $events->links() }}
        </div>
    @else
        <x-empty-state 
            title="События не найдены" 
            description="Пока нет опубликованных событий"
        />
    @endif
@endsection
