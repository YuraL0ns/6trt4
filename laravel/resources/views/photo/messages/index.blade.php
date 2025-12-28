@extends('layouts.app')

@section('title', 'Сообщения - Hunter-Photo.Ru')
@section('page-title', 'Сообщения')

@section('content')
    @if($conversations->count() > 0)
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2">
                <x-card>
                    <div class="space-y-4">
                        @foreach($conversations as $conversation)
                            <a href="{{ route('photo.messages.show', $conversation->user_id) }}" class="block p-4 bg-[#121212] rounded-lg hover:bg-[#1e1e1e] transition-colors">
                                <div class="flex items-center space-x-4">
                                    <div class="w-12 h-12 bg-[#a78bfa] rounded-full flex items-center justify-center flex-shrink-0">
                                        <span class="text-white font-semibold">
                                            {{ strtoupper(substr($conversation->user->last_name, 0, 1) . substr($conversation->user->first_name, 0, 1)) }}
                                        </span>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="font-semibold text-white truncate">{{ $conversation->user->full_name }}</p>
                                        <p class="text-sm text-gray-400">{{ $conversation->last_message_at->format('d.m.Y H:i') }}</p>
                                    </div>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </x-card>
            </div>
        </div>
    @else
        <x-empty-state 
            title="Нет сообщений" 
            description="У вас пока нет сообщений от пользователей"
        />
    @endif
@endsection


