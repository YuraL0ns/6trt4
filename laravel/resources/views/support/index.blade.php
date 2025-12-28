@extends('layouts.app')

@section('title', 'Техподдержка - Hunter-Photo.Ru')
@section('page-title', 'Техподдержка')

@section('content')
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2">
            @if($tickets->count() > 0)
                <div class="space-y-4">
                    @foreach($tickets as $ticket)
                        <x-card>
                            <div class="flex items-center justify-between mb-4">
                                <div>
                                    <h3 class="font-semibold text-white mb-1">{{ $ticket->subject }}</h3>
                                    <p class="text-sm text-gray-400">
                                        {{ $ticket->created_at->format('d F Y, H:i') }}
                                    </p>
                                </div>
                                <div class="flex items-center space-x-3">
                                    <x-badge variant="{{ $ticket->status === 'open' ? 'warning' : 'success' }}">
                                        {{ $ticket->status === 'open' ? 'Открыт' : 'Закрыт' }}
                                    </x-badge>
                                    @if($ticket->last_replied_by && $ticket->last_replied_by !== auth()->id())
                                        <span class="w-3 h-3 bg-[#a78bfa] rounded-full"></span>
                                    @endif
                                </div>
                            </div>
                            
                            <p class="text-gray-300 mb-4 line-clamp-2">
                                {{ $ticket->messages->first()->message ?? '' }}
                            </p>
                            
                            <x-button href="{{ route('support.show', $ticket->id) }}" variant="outline" size="sm">
                                Открыть
                            </x-button>
                        </x-card>
                    @endforeach
                </div>

                <div class="mt-6">
                    {{ $tickets->links() }}
                </div>
            @else
                <x-empty-state 
                    title="Нет обращений" 
                    description="У вас пока нет обращений в техподдержку"
                />
            @endif
        </div>

        <div>
            <x-card title="Создать обращение">
                <form action="{{ route('support.store') }}" method="POST" class="space-y-4">
                    @csrf
                    
                    <x-input label="Тема" name="subject" placeholder="Тема обращения" required />
                    
                    <x-select 
                        label="Тип обращения" 
                        name="type" 
                        :options="[
                            'technical' => 'Технический вопрос',
                            'payment' => 'Проблема при оплате',
                            'photographer' => 'Проблема с фотографом',
                            'other' => 'Другое'
                        ]"
                        required 
                    />
                    
                    <x-textarea label="Сообщение" name="message" rows="6" placeholder="Опишите вашу проблему" required />
                    
                    <x-button type="submit" class="w-full">Отправить</x-button>
                </form>
            </x-card>
        </div>
    </div>
@endsection
