@extends('layouts.app')

@section('title', 'Заявки на вывод - Hunter-Photo.Ru')
@section('page-title', 'Заявки на вывод средств')

@section('content')
    @if($withdrawals->count() > 0)
        <x-table :headers="['Фотограф', 'Сумма', 'Тип', 'Статус', 'Дата', 'Действия']">
            @foreach($withdrawals as $withdrawal)
                <tr class="hover:bg-[#1e1e1e] transition-colors">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm font-medium text-white">{{ $withdrawal->photographer->full_name }}</div>
                        <div class="text-sm text-gray-400">{{ $withdrawal->photographer->email }}</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-white font-semibold">
                        {{ number_format($withdrawal->amount, 0, ',', ' ') }} ₽
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">
                        {{ $withdrawal->type === 'legal' ? 'Юр. лицо' : 'Физ. лицо' }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <x-badge variant="{{ $withdrawal->status === 'completed' ? 'success' : ($withdrawal->status === 'approved' ? 'info' : ($withdrawal->status === 'rejected' ? 'error' : 'warning')) }}">
                            @if($withdrawal->status === 'pending') Ожидание
                            @elseif($withdrawal->status === 'approved') Одобрено
                            @elseif($withdrawal->status === 'rejected') Отклонено
                            @else Завершено
                            @endif
                        </x-badge>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-400">
                        {{ $withdrawal->created_at->format('d.m.Y H:i') }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                        <div class="flex space-x-2">
                            <x-button href="{{ route('admin.withdrawals.show', $withdrawal->id) }}" size="sm" variant="outline">
                                Подробнее
                            </x-button>
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-table>

        <div class="mt-6">
            {{ $withdrawals->links() }}
        </div>
    @else
        <x-empty-state 
            title="Нет заявок" 
            description="Нет заявок на вывод средств"
        />
    @endif
@endsection


