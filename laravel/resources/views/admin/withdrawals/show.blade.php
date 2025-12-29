@extends('layouts.app')

@php
    use Illuminate\Support\Facades\Storage;
@endphp

@section('title', 'Заявка на вывод - Hunter-Photo.Ru')
@section('page-title', 'Заявка на вывод средств')

@section('content')
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2">
            <x-card title="Информация о заявке">
                <div class="space-y-4">
                    <div>
                        <p class="text-sm text-gray-400 mb-1">Фотограф</p>
                        <p class="text-white font-semibold">{{ $withdrawal->photographer->full_name }}</p>
                        <p class="text-sm text-gray-400">{{ $withdrawal->photographer->email }}</p>
                    </div>
                    
                    <div>
                        <p class="text-sm text-gray-400 mb-1">Сумма</p>
                        <p class="text-2xl font-bold text-[#a78bfa]">{{ number_format($withdrawal->amount, 0, ',', ' ') }} ₽</p>
                    </div>
                    
                    <div>
                        <p class="text-sm text-gray-400 mb-1">Тип получателя</p>
                        <p class="text-white">{{ $withdrawal->type === 'legal' ? 'Юридическое лицо' : 'Физическое лицо' }}</p>
                    </div>
                    
                    @if($withdrawal->type === 'legal')
                        <div class="p-4 bg-[#121212] rounded-lg space-y-2">
                            <p class="text-sm text-gray-400">ИНН: <span class="text-white">{{ $withdrawal->inn }}</span></p>
                            <p class="text-sm text-gray-400">КПП: <span class="text-white">{{ $withdrawal->kpp }}</span></p>
                            <p class="text-sm text-gray-400">Расчетный счет: <span class="text-white">{{ $withdrawal->account }}</span></p>
                            <p class="text-sm text-gray-400">Банк: <span class="text-white">{{ $withdrawal->bank }}</span></p>
                            <p class="text-sm text-gray-400">Организация: <span class="text-white">{{ $withdrawal->organization_name }}</span></p>
                        </div>
                    @else
                        <div class="p-4 bg-[#121212] rounded-lg space-y-2">
                            <p class="text-sm text-gray-400">Тип перевода: <span class="text-white">
                                @if($withdrawal->transfer_type === 'sbp') СБП
                                @elseif($withdrawal->transfer_type === 'card') Карта
                                @else Лицевой счет
                                @endif
                            </span></p>
                            @if($withdrawal->phone)
                                <p class="text-sm text-gray-400">Телефон: <span class="text-white">{{ $withdrawal->phone }}</span></p>
                            @endif
                            @if($withdrawal->card_number)
                                <p class="text-sm text-gray-400">Номер карты: <span class="text-white">{{ $withdrawal->card_number }}</span></p>
                            @endif
                            @if($withdrawal->account_number)
                                <p class="text-sm text-gray-400">Лицевой счет: <span class="text-white">{{ $withdrawal->account_number }}</span></p>
                            @endif
                            @if($withdrawal->bank_name)
                                <p class="text-sm text-gray-400">Банк: <span class="text-white">{{ $withdrawal->bank_name }}</span></p>
                            @endif
                        </div>
                    @endif
                    
                    <div>
                        <p class="text-sm text-gray-400 mb-1">Баланс до вывода</p>
                        <p class="text-white">{{ number_format($withdrawal->balance_before, 0, ',', ' ') }} ₽</p>
                    </div>
                    
                    <div>
                        <p class="text-sm text-gray-400 mb-1">Баланс после вывода</p>
                        <p class="text-white">{{ number_format($withdrawal->balance_after, 0, ',', ' ') }} ₽</p>
                    </div>
                    
                    {{-- Чеки --}}
                    <div class="pt-4 border-t border-gray-700 space-y-3">
                        <div>
                            <p class="text-sm text-gray-400 mb-2">Чеки</p>
                            <div class="space-y-2">
                                <div class="flex items-center justify-between p-3 bg-[#121212] rounded-lg">
                                    <span class="text-sm text-gray-300">Чек администратора (отправка средств):</span>
                                    @if($withdrawal->receipt_path_admin)
                                        <a href="{{ route('admin.withdrawals.receipt', ['id' => $withdrawal->id, 'type' => 'admin']) }}" target="_blank" class="text-[#a78bfa] hover:text-[#8b5cf6] text-sm">
                                            Просмотреть
                                        </a>
                                    @else
                                        <span class="text-sm text-gray-500">Не загружен</span>
                                    @endif
                                </div>
                                <div class="flex items-center justify-between p-3 bg-[#121212] rounded-lg">
                                    <span class="text-sm text-gray-300">Чек пользователя (получение средств):</span>
                                    @if($withdrawal->receipt_path_photographer)
                                        <a href="{{ route('admin.withdrawals.receipt', ['id' => $withdrawal->id, 'type' => 'photographer']) }}" target="_blank" class="text-[#a78bfa] hover:text-[#8b5cf6] text-sm">
                                            Просмотреть
                                        </a>
                                    @else
                                        <span class="text-sm text-gray-500">Не загружен</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </x-card>
            
            @if($withdrawal->status === 'pending')
                <x-card title="Обработка заявки" class="mt-6">
                    <form action="{{ route('admin.withdrawals.approve', $withdrawal->id) }}" method="POST" enctype="multipart/form-data" class="space-y-4">
                        @csrf
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Загрузить чек администратора (чек об отправке средств)</label>
                            <input type="file" name="receipt" accept=".pdf,.jpg,.jpeg,.png" class="w-full px-4 py-2 bg-[#121212] border border-gray-700 rounded-lg text-white" required>
                            <p class="text-xs text-gray-400 mt-1">Форматы: PDF, JPG, PNG. Максимальный размер: 10 МБ</p>
                        </div>
                        <div class="flex space-x-3">
                            <x-button type="submit" class="flex-1">Одобрить и загрузить чек</x-button>
                            <x-button href="{{ route('admin.withdrawals.reject', $withdrawal->id) }}" variant="danger" type="button">
                                Отклонить
                            </x-button>
                        </div>
                    </form>
                </x-card>
            @endif
        </div>

        <div>
            <x-card title="Статистика фотографа">
                <div class="space-y-3">
                    <div>
                        <p class="text-sm text-gray-400">Общий заработок</p>
                        <p class="text-lg font-bold text-white">{{ number_format($photographerStats['total_earnings'], 0, ',', ' ') }} ₽</p>
                    </div>
                </div>
            </x-card>
            
            <x-card title="История выводов" class="mt-6">
                @if($photographerStats['withdrawals_history']->count() > 0)
                    <div class="space-y-2">
                        @foreach($photographerStats['withdrawals_history'] as $hist)
                            <div class="p-3 bg-[#121212] rounded-lg">
                                <div class="flex justify-between">
                                    <span class="text-sm text-white">{{ number_format($hist->amount, 0, ',', ' ') }} ₽</span>
                                    <x-badge variant="{{ $hist->status === 'completed' ? 'success' : 'warning' }}" size="sm">
                                        {{ $hist->status === 'completed' ? 'Завершено' : 'В процессе' }}
                                    </x-badge>
                                </div>
                                <p class="text-xs text-gray-400 mt-1">{{ $hist->created_at->format('d.m.Y') }}</p>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-gray-400 text-center py-4">Нет истории</p>
                @endif
            </x-card>
        </div>
    </div>
@endsection


