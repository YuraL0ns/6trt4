@extends('layouts.app')

@section('title', 'Главная - Hunter-Photo.Ru')
@section('page-title', 'Главная')

@section('content')
    <!-- Hero Section -->
    <div class="mb-8">
        <div class="bg-gradient-to-r from-[#a78bfa] to-[#8b5cf6] rounded-lg p-8 text-center">
            <h1 class="text-4xl font-bold text-white mb-4">Hunter-Photo.Ru</h1>
            <p class="text-xl text-white/90 mb-6">Платформа для продажи фотографий со спортивных мероприятий</p>
            <x-button href="{{ route('events.index') }}" size="lg">
                Посмотреть все спортивные события
            </x-button>
        </div>
    </div>

    <!-- Как работает система -->
    <div class="mb-12">
        <h2 class="text-3xl font-bold text-white mb-8 text-center">Как работает система</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <x-card class="text-center">
                <div class="w-16 h-16 bg-[#a78bfa] rounded-full flex items-center justify-center mx-auto mb-4">
                    <span class="text-white text-2xl font-bold">1</span>
                </div>
                <h3 class="text-xl font-semibold text-white mb-2">Выбираете событие</h3>
                <p class="text-gray-400">Просматривайте доступные спортивные события и выбирайте интересующее вас</p>
            </x-card>
            
            <x-card class="text-center">
                <div class="w-16 h-16 bg-[#a78bfa] rounded-full flex items-center justify-center mx-auto mb-4">
                    <span class="text-white text-2xl font-bold">2</span>
                </div>
                <h3 class="text-xl font-semibold text-white mb-2">Выполняете поиск фотографии</h3>
                <p class="text-gray-400">Загрузите свое селфи или укажите номер для поиска ваших фотографий</p>
            </x-card>
            
            <x-card class="text-center">
                <div class="w-16 h-16 bg-[#a78bfa] rounded-full flex items-center justify-center mx-auto mb-4">
                    <span class="text-white text-2xl font-bold">3</span>
                </div>
                <h3 class="text-xl font-semibold text-white mb-2">Покупаете фотографию</h3>
                <p class="text-gray-400">Добавьте понравившиеся фотографии в корзину и оплатите заказ</p>
            </x-card>
        </div>
    </div>

    <!-- Latest Events -->
    <div class="mb-8">
        <h2 class="text-2xl font-bold text-white mb-6">Последние события</h2>
        
        @php
            $events = \App\Models\Event::where('status', 'published')
                ->with('author')
                ->orderBy('date', 'asc')
                ->orderBy('created_at', 'desc')
                ->limit(12)
                ->get();
        @endphp
        
        @if($events->count() > 0)
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                @foreach($events as $event)
                    <x-card>
                        @if($event->cover_path)
                            @php
                                $coverPath = $event->cover_path;
                                $fullCoverPath = storage_path('app/public/' . $coverPath);
                                $coverUrl = file_exists($fullCoverPath) ? Storage::url($coverPath) : null;
                            @endphp
                            @if($coverUrl)
                                <img src="{{ $coverUrl }}" alt="{{ $event->title }}" class="w-full aspect-video object-cover rounded-lg mb-4">
                            @else
                                <div class="w-full aspect-video bg-gray-800 rounded-lg mb-4 flex items-center justify-center text-gray-500 text-sm">
                                    Нет обложки
                                </div>
                            @endif
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
                        <p class="text-sm text-gray-400 mb-2">{{ $event->city }}</p>
                        <p class="text-sm text-gray-400 mb-4">{{ $event->getFormattedDate() }}</p>
                        <x-button href="{{ route('events.show', $event->slug ?? $event->id) }}" size="sm" class="w-full">
                            Просмотреть
                        </x-button>
                    </x-card>
                @endforeach
            </div>
        @else
            <x-card>
                <p class="text-gray-400 text-center py-8">События пока не добавлены</p>
            </x-card>
        @endif
    </div>
@endsection


