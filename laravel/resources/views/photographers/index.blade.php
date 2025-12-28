@extends('layouts.app')

@section('title', 'Фотографы - Hunter-Photo.Ru')
@section('page-title', 'Фотографы')
@section('page-description', 'Наши фотографы')

@php
    use Illuminate\Support\Str;
@endphp

@section('content')
    @if($photographers->count() > 0)
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($photographers as $photographer)
                <x-card>
                    <div class="flex items-center space-x-4 mb-4">
                        <div class="w-16 h-16 bg-[#a78bfa] rounded-full flex items-center justify-center flex-shrink-0">
                            @if($photographer->avatar)
                                <img src="{{ Storage::url($photographer->avatar) }}" alt="{{ $photographer->full_name }}" class="w-full h-full rounded-full object-cover">
                            @else
                                <span class="text-white text-xl font-semibold">
                                    {{ strtoupper(substr($photographer->last_name, 0, 1) . substr($photographer->first_name, 0, 1)) }}
                                </span>
                            @endif
                        </div>
                        <div class="flex-1 min-w-0">
                            <h3 class="font-semibold text-white truncate">{{ $photographer->full_name }}</h3>
                            <p class="text-sm text-gray-400">{{ $photographer->events_count }} событий</p>
                        </div>
                    </div>
                    
                    @if($photographer->description)
                        <p class="text-sm text-gray-300 mb-4 line-clamp-3">{{ Str::limit($photographer->description, 150) }}</p>
                    @endif
                    
                    @if($photographer->hash_login)
                        <x-button href="{{ route('photographers.show', $photographer->hash_login) }}" size="sm" class="w-full">
                            Посмотреть профиль
                        </x-button>
                    @else
                        <x-button disabled size="sm" class="w-full opacity-50 cursor-not-allowed">
                            Профиль недоступен
                        </x-button>
                    @endif
                </x-card>
            @endforeach
        </div>

        <div class="mt-6">
            {{ $photographers->links() }}
        </div>
    @else
        <x-empty-state 
            title="Фотографы не найдены" 
            description="Пока нет зарегистрированных фотографов"
        />
    @endif
@endsection
