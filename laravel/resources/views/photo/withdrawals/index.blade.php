@extends('layouts.app')

@section('title', 'Вывод средств - Hunter-Photo.Ru')
@section('page-title', 'Вывод средств')

@section('content')
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
        <x-card>
            <p class="text-sm text-gray-400 mb-1">Текущий баланс</p>
            <p class="text-3xl font-bold text-white" id="current-balance">{{ number_format($balance ?? 0, 2, ',', ' ') }} ₽</p>
            <button onclick="refreshBalance()" class="mt-2 text-sm text-[#a78bfa] hover:text-[#8b5cf6] transition-colors">
                Обновить баланс
            </button>
        </x-card>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <x-card title="Создать заявку на вывод">
            <form id="withdrawal-form" action="{{ route('photo.withdrawals.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                
                <x-input label="Сумма к выводу (₽)" name="amount" type="number" min="1" :max="$balance ?? 0" step="0.01" required id="withdrawal-amount" />
                
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
                    
                    <x-input label="Номер телефона" name="phone" id="phone-field" class="hidden" />
                    <x-input label="Номер карты" name="card_number" id="card-field" class="hidden" />
                    <x-input label="Номер лицевого счета" name="account_number" id="account-field" class="hidden" />
                    <x-input label="Наименование банка" name="bank_name" id="bank-name-field" class="hidden" />
                    <x-textarea label="Комментарий (необязательно)" name="account_comment" id="account-comment-field" class="hidden" />
                </div>
                
                <!-- Поле для загрузки чека от пользователя (необязательно при создании) -->
                <div class="mt-4">
                    <label class="block text-sm font-medium text-gray-300 mb-2">
                        Чек о получении средств (можно загрузить позже)
                    </label>
                    <input 
                        type="file" 
                        name="receipt_photographer" 
                        accept=".pdf,.jpg,.jpeg,.png"
                        class="block w-full text-sm text-gray-300 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-[#a78bfa] file:text-white hover:file:bg-[#8b5cf6] transition-colors"
                    >
                    <p class="text-xs text-gray-400 mt-1">Форматы: PDF, JPG, PNG. Максимальный размер: 10 МБ</p>
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
                            <p class="text-sm text-gray-400 mb-2">{{ $withdrawal->created_at->format('d.m.Y H:i') }}</p>
                            
                            {{-- Чеки --}}
                            <div class="mt-3 space-y-2 border-t border-gray-700 pt-3">
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-gray-400">Чек администратора:</span>
                                    @if($withdrawal->receipt_path_admin)
                                        <a href="{{ route('photo.withdrawals.receipt', ['id' => $withdrawal->id, 'type' => 'admin']) }}" target="_blank" class="text-[#a78bfa] hover:text-[#8b5cf6]">
                                            Просмотреть
                                        </a>
                                    @else
                                        <span class="text-gray-500">Не загружен</span>
                                    @endif
                                </div>
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-gray-400">Чек пользователя:</span>
                                    @if($withdrawal->receipt_path_photographer)
                                        <a href="{{ route('photo.withdrawals.receipt', ['id' => $withdrawal->id, 'type' => 'photographer']) }}" target="_blank" class="text-[#a78bfa] hover:text-[#8b5cf6]">
                                            Просмотреть
                                        </a>
                                    @else
                                        @if($withdrawal->status === 'approved' || $withdrawal->status === 'completed')
                                            <form action="{{ route('photo.withdrawals.upload-receipt', $withdrawal->id) }}" method="POST" enctype="multipart/form-data" class="inline">
                                                @csrf
                                                <input type="file" name="receipt_photographer" accept=".pdf,.jpg,.jpeg,.png" required class="hidden" id="receipt-{{ $withdrawal->id }}" onchange="this.form.submit()">
                                                <button type="button" onclick="document.getElementById('receipt-{{ $withdrawal->id }}').click()" class="text-[#a78bfa] hover:text-[#8b5cf6]">
                                                    Загрузить чек
                                                </button>
                                            </form>
                                        @else
                                            <span class="text-gray-500">Не загружен</span>
                                        @endif
                                    @endif
                                </div>
                            </div>
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
// КРИТИЧЕСКОЕ ИСПРАВЛЕНИЕ: Функция для обновления баланса
function refreshBalance() {
    fetch('{{ route("photo.withdrawals.balance") }}', {
        method: 'GET',
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.balance !== undefined) {
            document.getElementById('current-balance').textContent = 
                new Intl.NumberFormat('ru-RU', { 
                    minimumFractionDigits: 2, 
                    maximumFractionDigits: 2 
                }).format(data.balance) + ' ₽';
            
            // Обновляем max атрибут поля amount
            const amountInput = document.querySelector('input[name="amount"]');
            if (amountInput) {
                amountInput.setAttribute('max', data.balance);
            }
        }
    })
    .catch(error => {
        console.error('Error refreshing balance:', error);
    });
}

// Обновляем баланс при загрузке страницы и после успешной отправки формы
document.addEventListener('DOMContentLoaded', function() {
    // Обновляем баланс каждые 30 секунд
    setInterval(refreshBalance, 30000);
    
    // Инициализируем форму при загрузке страницы
    handleTypeChange();
    handleTransferTypeChange();
    
    // Обновляем баланс после успешной отправки формы
    const form = document.getElementById('withdrawal-form');
    if (form) {
        form.addEventListener('submit', function(e) {
            // Не прерываем отправку формы, просто планируем обновление баланса после успеха
            setTimeout(function() {
                // Проверяем, есть ли сообщение об успехе (это означает, что форма была успешно отправлена)
                const successMessage = document.querySelector('.alert-success, [role="alert"]');
                if (successMessage) {
                    refreshBalance();
                }
            }, 1000);
        });
    }
});

// Функция для обработки изменения типа получателя
function handleTypeChange() {
    const typeSelect = document.querySelector('select[name="type"]');
    if (!typeSelect) return;
    
    const type = typeSelect.value;
    const legalFields = document.getElementById('legal-fields');
    const individualFields = document.getElementById('individual-fields');
    
    // Скрываем все поля физ.лица при смене типа
    if (individualFields) {
        const allIndividualFields = individualFields.querySelectorAll('[id$="-field"]');
        allIndividualFields.forEach(field => {
            field.classList.add('hidden');
            const input = field.querySelector('input, textarea, select');
            if (input) {
                input.removeAttribute('required');
                input.value = '';
            }
        });
    }
    
    if (type === 'legal') {
        if (legalFields) legalFields.classList.remove('hidden');
        if (individualFields) individualFields.classList.add('hidden');
    } else if (type === 'individual') {
        if (legalFields) legalFields.classList.add('hidden');
        if (individualFields) individualFields.classList.remove('hidden');
    } else {
        if (legalFields) legalFields.classList.add('hidden');
        if (individualFields) individualFields.classList.add('hidden');
    }
}

// Обработчик изменения типа получателя
const typeSelect = document.querySelector('select[name="type"]');
if (typeSelect) {
    typeSelect.addEventListener('change', handleTypeChange);
    // Вызываем при загрузке страницы, если уже выбран тип
    if (typeSelect.value) {
        handleTypeChange();
    }
}

// Функция для обработки изменения типа перевода
function handleTransferTypeChange() {
    const transferTypeSelect = document.querySelector('select[name="transfer_type"]');
    if (!transferTypeSelect) return;
    
    const transferType = transferTypeSelect.value;
    
    // Получаем все поля
    const phoneField = document.getElementById('phone-field');
    const cardField = document.getElementById('card-field');
    const accountField = document.getElementById('account-field');
    const bankNameField = document.getElementById('bank-name-field');
    const accountCommentField = document.getElementById('account-comment-field');
    
    // Скрываем все поля и убираем required, очищаем значения
    [phoneField, cardField, accountField, bankNameField, accountCommentField].forEach(field => {
        if (field) {
            field.classList.add('hidden');
            const input = field.querySelector('input, textarea');
            if (input) {
                input.removeAttribute('required');
                input.value = ''; // Очищаем значение
            }
        }
    });
    
    // Показываем нужные поля и добавляем required
    if (transferType === 'sbp') {
        // СБП: телефон + наименование банка
        if (phoneField) {
            phoneField.classList.remove('hidden');
            const input = phoneField.querySelector('input');
            if (input) input.setAttribute('required', 'required');
        }
        if (bankNameField) {
            bankNameField.classList.remove('hidden');
            const input = bankNameField.querySelector('input');
            if (input) input.setAttribute('required', 'required');
        }
    } else if (transferType === 'card') {
        // Карта: номер карты + наименование банка (телефон НЕ нужен)
        if (cardField) {
            cardField.classList.remove('hidden');
            const input = cardField.querySelector('input');
            if (input) input.setAttribute('required', 'required');
        }
        if (bankNameField) {
            bankNameField.classList.remove('hidden');
            const input = bankNameField.querySelector('input');
            if (input) input.setAttribute('required', 'required');
        }
    } else if (transferType === 'account') {
        // Лицевой счет: номер счета + наименование банка (обязательно), комментарий (необязательно)
        if (accountField) {
            accountField.classList.remove('hidden');
            const input = accountField.querySelector('input');
            if (input) input.setAttribute('required', 'required');
        }
        if (bankNameField) {
            bankNameField.classList.remove('hidden');
            const input = bankNameField.querySelector('input');
            if (input) input.setAttribute('required', 'required');
        }
        if (accountCommentField) {
            accountCommentField.classList.remove('hidden');
            // Комментарий необязателен, required не добавляем
        }
    }
}

// Обработчик изменения типа перевода
const transferTypeSelect = document.querySelector('select[name="transfer_type"]');
if (transferTypeSelect) {
    transferTypeSelect.addEventListener('change', handleTransferTypeChange);
    // Вызываем при загрузке страницы, если уже выбран тип
    if (transferTypeSelect.value) {
        handleTransferTypeChange();
    }
}
</script>
@endpush


