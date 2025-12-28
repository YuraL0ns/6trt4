@extends('layouts.app')

@section('title', 'Мои события - Hunter-Photo.Ru')
@section('page-title', 'Мои события')

@section('content')
    <div class="mb-6">
        <x-button href="{{ route('photo.events.create') }}">
            <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
            </svg>
            Добавить событие
        </x-button>
    </div>

    @if($events->count() > 0)
        <x-table :headers="['Название', 'Место', 'Цена за фото', 'Всего фотографий', 'Статус', 'Действия']">
            @foreach($events as $event)
                <tr class="hover:bg-[#1e1e1e] transition-colors">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm font-medium text-white">{{ $event->title }}</div>
                        <div class="text-sm text-gray-400">{{ $event->date->format('d.m.Y') }}</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">{{ $event->city }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-white font-semibold">{{ number_format($event->price, 0, ',', ' ') }} ₽</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">{{ $event->photos_count }}</td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <x-badge variant="{{ $event->status === 'published' ? 'success' : ($event->status === 'processing' ? 'warning' : 'default') }}">
                            @if($event->status === 'draft') Черновик
                            @elseif($event->status === 'processing') В обработке
                            @elseif($event->status === 'published') Опубликовано
                            @else Завершено
                            @endif
                        </x-badge>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                        <x-button href="{{ route('photo.events.show', $event->slug ?? $event->id) }}" size="sm" variant="outline">
                            Открыть
                        </x-button>
                    </td>
                </tr>
            @endforeach
        </x-table>

        <div class="mt-6">
            {{ $events->links() }}
        </div>
    @else
        <x-empty-state 
            title="Нет событий" 
            description="Создайте ваше первое событие"
        >
            <x-slot:action>
                <x-button href="{{ route('photo.events.create') }}">Создать событие</x-button>
            </x-slot:action>
        </x-empty-state>
    @endif
@endsection


