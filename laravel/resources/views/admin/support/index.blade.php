@extends('layouts.app')

@section('title', 'Техподдержка - Hunter-Photo.Ru')
@section('page-title', 'Техподдержка')

@section('content')
    @if($tickets->count() > 0)
        <x-table :headers="['Тема', 'Пользователь', 'Тип', 'Статус', 'Последний ответ', 'Дата', 'Действия']">
            @foreach($tickets as $ticket)
                <tr class="hover:bg-[#1e1e1e] transition-colors">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm font-medium text-white">{{ $ticket->subject }}</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">{{ $ticket->user->full_name }}</td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <x-badge variant="default" size="sm">
                            @if($ticket->type === 'technical') Технический
                            @elseif($ticket->type === 'payment') Оплата
                            @elseif($ticket->type === 'photographer') Фотограф
                            @else Другое
                            @endif
                        </x-badge>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <x-badge variant="{{ $ticket->status === 'open' ? 'warning' : 'success' }}" size="sm">
                            {{ $ticket->status === 'open' ? 'Открыт' : 'Закрыт' }}
                        </x-badge>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-400">
                        @if($ticket->last_replied_by)
                            {{ $ticket->lastRepliedBy->full_name }}
                        @else
                            -
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-400">
                        {{ $ticket->created_at->format('d.m.Y H:i') }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                        <x-button href="{{ route('admin.support.show', $ticket->id) }}" size="sm" variant="outline">
                            Открыть
                        </x-button>
                    </td>
                </tr>
            @endforeach
        </x-table>

        <div class="mt-6">
            {{ $tickets->links() }}
        </div>
    @else
        <x-empty-state 
            title="Нет обращений" 
            description="Нет обращений в техподдержку"
        />
    @endif
@endsection


