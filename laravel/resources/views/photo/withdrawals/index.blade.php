@extends('layouts.app')

@section('title', 'Вывод средств - Hunter-Photo.Ru')
@section('page-title', 'Вывод средств')

@section('content')
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
        <x-card>
            <p class="text-sm text-gray-400 mb-1">Текущий баланс</p>
            <p class="text-3xl font-bold text-white">{{ number_format($balance, 0, ',', ' ') }} ₽</p>
        </x-card>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <x-card title="Создать заявку на вывод">
            <form action="{{ route('photo.withdrawals.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                
                <x-input label="Сумма к выводу (₽)" name="amount" type="number" min="1" :max="$balance" step="0.01" required />
                
                <x-select 
                    label="Тип получателя" 
                    name="type" 
                    :options="[
                        'individual' => 'Физическое лицо',
                        'legal' => 'Юридическое лицо'
                    ]"
                    required 
                />
                
                <!-- Поля для юр.лица -->
                <div id="legal-fields" class="hidden space-y-4">
                    <x-input label="ИНН" name="inn" />
                    <x-input label="КПП" name="kpp" />
                    <x-input label="Расчетный счет" name="account" />
                    <x-input label="Банк" name="bank" />
                    <x-input label="Наименование организации" name="organization_name" />
                </div>
                
                <!-- Поля для физ.лица -->
                <div id="individual-fields" class="hidden space-y-4">
                    <x-select 
                        label="Тип перевода" 
                        name="transfer_type" 
                        :options="[
                            'sbp' => 'СБП',
                            'card' => 'Номер карты',
                            'account' => 'Лицевой счет'
                        ]"
                    />
                    
                    <x-input label="Номер телефона (для СБП)" name="phone" id="phone-field" class="hidden" />
                    <x-input label="Номер карты" name="card_number" id="card-field" class="hidden" />
                    <x-input label="Номер лицевого счета" name="account_number" id="account-field" class="hidden" />
                    <x-input label="Наименование банка (для СБП)" name="bank_name" id="bank-name-field" class="hidden" />
                    <x-textarea label="Комментарий (для лицевого счета)" name="account_comment" id="account-comment-field" class="hidden" />
                </div>
                
                @if($errors->any())
                    <x-alert type="error" class="mb-4">
                        <ul class="list-disc list-inside">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </x-alert>
                @endif
                
                <x-button type="submit" class="w-full">Создать заявку</x-button>
            </form>
        </x-card>

        <x-card title="История заявок">
            @if($withdrawals->count() > 0)
                <div class="space-y-4">
                    @foreach($withdrawals as $withdrawal)
                        <div class="p-4 bg-[#121212] rounded-lg">
                            <div class="flex items-center justify-between mb-2">
                                <span class="font-semibold text-white">{{ number_format($withdrawal->amount, 0, ',', ' ') }} ₽</span>
                                <x-badge variant="{{ $withdrawal->status === 'completed' ? 'success' : ($withdrawal->status === 'approved' ? 'info' : ($withdrawal->status === 'rejected' ? 'error' : 'warning')) }}">
                                    @if($withdrawal->status === 'pending') Ожидание
                                    @elseif($withdrawal->status === 'approved') Одобрено
                                    @elseif($withdrawal->status === 'rejected') Отклонено
                                    @else Завершено
                                    @endif
                                </x-badge>
                            </div>
                            <p class="text-sm text-gray-400">{{ $withdrawal->created_at->format('d.m.Y H:i') }}</p>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-gray-400 text-center py-8">Нет заявок</p>
            @endif
        </x-card>
    </div>
@endsection

@push('scripts')
<script>
document.querySelector('select[name="type"]').addEventListener('change', function(e) {
    const type = e.target.value;
    const legalFields = document.getElementById('legal-fields');
    const individualFields = document.getElementById('individual-fields');
    
    if (type === 'legal') {
        legalFields.classList.remove('hidden');
        individualFields.classList.add('hidden');
    } else {
        legalFields.classList.add('hidden');
        individualFields.classList.remove('hidden');
    }
});

document.querySelector('select[name="transfer_type"]')?.addEventListener('change', function(e) {
    const transferType = e.target.value;
    
    // Скрываем все поля
    document.getElementById('phone-field')?.classList.add('hidden');
    document.getElementById('card-field')?.classList.add('hidden');
    document.getElementById('account-field')?.classList.add('hidden');
    document.getElementById('bank-name-field')?.classList.add('hidden');
    document.getElementById('account-comment-field')?.classList.add('hidden');
    
    // Показываем нужные поля
    if (transferType === 'sbp') {
        document.getElementById('phone-field')?.classList.remove('hidden');
        document.getElementById('bank-name-field')?.classList.remove('hidden');
    } else if (transferType === 'card') {
        document.getElementById('card-field')?.classList.remove('hidden');
    } else if (transferType === 'account') {
        document.getElementById('account-field')?.classList.remove('hidden');
        document.getElementById('account-comment-field')?.classList.remove('hidden');
    }
});
</script>
@endpush


