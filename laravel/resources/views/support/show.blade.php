@extends('layouts.app')

@section('title', $ticket->subject . ' - Техподдержка')
@section('page-title', $ticket->subject)

@section('content')
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2">
            <x-card>
                <div class="space-y-4">
                    @foreach($ticket->messages as $message)
                        <div class="flex {{ $message->user_id === auth()->id() ? 'justify-end' : 'justify-start' }}">
                            <div class="max-w-2xl {{ $message->user_id === auth()->id() ? 'bg-[#a78bfa]' : 'bg-[#1e1e1e]' }} rounded-lg p-4">
                                <div class="flex items-center space-x-2 mb-2">
                                    <span class="text-sm font-semibold {{ $message->user_id === auth()->id() ? 'text-white' : 'text-gray-300' }}">
                                        {{ $message->user->full_name }}
                                    </span>
                                    <span class="text-xs {{ $message->user_id === auth()->id() ? 'text-white/70' : 'text-gray-400' }}">
                                        {{ $message->created_at->format('d.m.Y H:i') }}
                                    </span>
                                </div>
                                <p class="{{ $message->user_id === auth()->id() ? 'text-white' : 'text-gray-300' }} whitespace-pre-line">
                                    {{ $message->message }}
                                </p>
                            </div>
                        </div>
                    @endforeach
                </div>

                @if($ticket->status === 'open')
                    <form action="{{ route('support.add-message', $ticket->id) }}" method="POST" class="mt-6">
                        @csrf
                        <x-textarea name="message" rows="4" placeholder="Ваш ответ..." required />
                        <x-button type="submit" class="mt-3">Отправить</x-button>
                    </form>
                @endif
            </x-card>
        </div>

        <div>
            <x-card title="Информация">
                <div class="space-y-3">
                    <div>
                        <p class="text-sm text-gray-400">Статус</p>
                        <x-badge variant="{{ $ticket->status === 'open' ? 'warning' : 'success' }}">
                            {{ $ticket->status === 'open' ? 'Открыт' : 'Закрыт' }}
                        </x-badge>
                    </div>
                    <div>
                        <p class="text-sm text-gray-400">Тип</p>
                        <p class="text-white">{{ ucfirst($ticket->type) }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-400">Создан</p>
                        <p class="text-white">{{ $ticket->created_at->format('d F Y, H:i') }}</p>
                    </div>
                </div>
            </x-card>
        </div>
    </div>
@endsection


