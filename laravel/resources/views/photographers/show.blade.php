@extends('layouts.app')

@section('title', $photographer->full_name . ' - Hunter-Photo.Ru')
@section('page-title', $photographer->full_name)

@php
    use Illuminate\Support\Facades\Storage;
    use Illuminate\Support\Str;
@endphp

@section('content')
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
        <div class="lg:col-span-2">
            <x-card>
                <div class="flex items-center space-x-6 mb-6">
                    <div class="w-24 h-24 bg-[#a78bfa] rounded-full flex items-center justify-center flex-shrink-0">
                        @if($photographer->avatar)
                            <img src="{{ Storage::url($photographer->avatar) }}" alt="{{ $photographer->full_name }}" class="w-full h-full rounded-full object-cover">
                        @else
                            <span class="text-white text-3xl font-semibold">
                                {{ strtoupper(substr($photographer->last_name, 0, 1) . substr($photographer->first_name, 0, 1)) }}
                            </span>
                        @endif
                    </div>
                    <div>
                        <h2 class="text-2xl font-bold text-white mb-2">{{ $photographer->full_name }}</h2>
                        @if($photographer->city)
                            <p class="text-gray-400">{{ $photographer->city }}</p>
                        @endif
                    </div>
                </div>
                
                @if($photographer->description)
                    <div class="prose prose-invert max-w-none">
                        <p class="text-gray-300 whitespace-pre-line">{{ $photographer->description }}</p>
                    </div>
                @endif
            </x-card>
        </div>

        <div>
            <x-card title="Контакты">
                @auth
                    <x-button href="{{ route('photo.messages.show', $photographer->id) }}" class="w-full mb-4">
                        Написать сообщение
                    </x-button>
                @else
                    <p class="text-sm text-gray-400 mb-4">Войдите, чтобы написать фотографу</p>
                    <x-button href="{{ route('login') }}" class="w-full">
                        Войти
                    </x-button>
                @endauth
            </x-card>
        </div>
    </div>

    <!-- События фотографа -->
    <div>
        <h2 class="text-2xl font-bold text-white mb-6">События фотографа</h2>
        
        @if($events->count() > 0)
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach($events as $event)
                    <x-card>
                        @if($event->cover_path)
                            <div class="aspect-video bg-gray-800 rounded-lg mb-4 overflow-hidden">
                                <img src="{{ Storage::url($event->cover_path) }}" alt="{{ $event->title }}" class="w-full h-full object-cover">
                            </div>
                        @endif
                        
                        <h3 class="text-lg font-semibold text-white mb-2">{{ $event->title }}</h3>
                        <p class="text-sm text-gray-400 mb-4">{{ $event->city }}, {{ $event->getFormattedDate() }}</p>
                        
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
                title="Нет событий" 
                description="У этого фотографа пока нет опубликованных событий"
            />
        @endif
    </div>
@endsection

