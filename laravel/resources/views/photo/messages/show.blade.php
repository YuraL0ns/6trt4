@extends('layouts.app')

@section('title', 'Чат с ' . $user->full_name . ' - Hunter-Photo.Ru')
@section('page-title', 'Чат с ' . $user->full_name)

@section('content')
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2">
            <x-card>
                <div class="space-y-4 mb-6" style="max-height: 500px; overflow-y: auto;">
                    @foreach($messages as $message)
                        <div class="flex {{ $message->photographer_id === auth()->id() ? 'justify-end' : 'justify-start' }}">
                            <div class="max-w-2xl {{ $message->photographer_id === auth()->id() ? 'bg-[#a78bfa]' : 'bg-[#1e1e1e]' }} rounded-lg p-4">
                                <div class="flex items-center space-x-2 mb-2">
                                    <span class="text-sm font-semibold {{ $message->photographer_id === auth()->id() ? 'text-white' : 'text-gray-300' }}">
                                        {{ $message->photographer_id === auth()->id() ? auth()->user()->full_name : $user->full_name }}
                                    </span>
                                    <span class="text-xs {{ $message->photographer_id === auth()->id() ? 'text-white/70' : 'text-gray-400' }}">
                                        {{ $message->created_at->format('d.m.Y H:i') }}
                                    </span>
                                </div>
                                <p class="{{ $message->photographer_id === auth()->id() ? 'text-white' : 'text-gray-300' }} whitespace-pre-line">
                                    {{ $message->message }}
                                </p>
                            </div>
                        </div>
                    @endforeach
                </div>

                <form action="{{ route('photo.messages.store', $user->id) }}" method="POST">
                    @csrf
                    <x-textarea name="message" rows="4" placeholder="Ваше сообщение..." required />
                    <x-button type="submit" class="mt-3">Отправить</x-button>
                </form>
            </x-card>
        </div>

        <div>
            <x-card title="Информация о пользователе">
                <div class="space-y-3">
                    <div>
                        <p class="text-sm text-gray-400">Имя</p>
                        <p class="text-white font-semibold">{{ $user->full_name }}</p>
                    </div>
                    @if($user->email)
                        <div>
                            <p class="text-sm text-gray-400">Email</p>
                            <p class="text-white">{{ $user->email }}</p>
                        </div>
                    @endif
                    @if($user->city)
                        <div>
                            <p class="text-sm text-gray-400">Город</p>
                            <p class="text-white">{{ $user->city }}</p>
                        </div>
                    @endif
                </div>
            </x-card>
        </div>
    </div>
@endsection


