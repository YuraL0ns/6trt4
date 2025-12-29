@extends('layouts.app')

@section('title', 'Заказ #' . substr($order->id, 0, 8) . ' - Hunter-Photo.Ru')
@section('page-title', 'Заказ #' . substr($order->id, 0, 8))

@php
    use Illuminate\Support\Facades\Storage;
@endphp

@section('content')
    <x-card class="mb-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <p class="text-sm text-gray-400 mb-1">Статус</p>
                <div id="order-status-container">
                    <x-badge variant="{{ $order->status === 'paid' ? 'success' : 'warning' }}" id="order-status-badge">
                        {{ $order->status === 'paid' ? 'Оплачен' : 'Ожидает оплаты' }}
                    </x-badge>
                </div>
            </div>
            <div>
                <p class="text-sm text-gray-400 mb-1">Сумма</p>
                <p class="text-2xl font-bold text-[#a78bfa]">{{ number_format($order->total_amount, 0, ',', ' ') }} ₽</p>
            </div>
            <div>
                <p class="text-sm text-gray-400 mb-1">Дата создания</p>
                <p class="text-white">{{ $order->created_at->format('d F Y, H:i') }}</p>
            </div>
            <div>
                <p class="text-sm text-gray-400 mb-1">Email</p>
                <p class="text-white">{{ $order->email }}</p>
            </div>
        </div>
    </x-card>

    <x-card title="Фотографии в заказе">
        @if($order->status !== 'paid')
            <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
                @foreach($order->items as $item)
                    <div class="aspect-square bg-gray-800 rounded-lg overflow-hidden">
                        <img src="{{ $item->photo->s3_custom_url ?? Storage::url($item->photo->custom_path) }}" alt="Photo" class="w-full h-full object-cover">
                    </div>
                @endforeach
            </div>
        @else
            <p class="text-sm text-gray-300 mb-4">
                Заказ оплачен. Вы можете скачать архив с оригинальными фотографиями без водяных знаков.
            </p>
        @endif
        
        @if($order->status === 'paid')
            @php
                $zipPath = $order->zip_path;
                $zipExists = false;
                if ($zipPath) {
                    $fullZipPath = storage_path('app/public/' . $zipPath);
                    $zipExists = file_exists($fullZipPath);
                }
            @endphp
            
            @if($zipPath && $zipExists)
                <div class="mt-6">
                    <x-button href="{{ route('orders.download', $order->id) }}" size="lg" class="w-full">
                        <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                        </svg>
                        Скачать архив с фотографиями
                    </x-button>
                </div>
            @else
                <div class="mt-6 p-4 bg-yellow-900/20 border border-yellow-700 rounded-lg">
                    <p class="text-yellow-400 text-sm">
                        @if(!$zipPath)
                            Архив создается, пожалуйста, обновите страницу через несколько секунд.
                        @else
                            Архив не найден. Пожалуйста, обратитесь в поддержку.
                        @endif
                    </p>
                </div>
            @endif
        @endif
    </x-card>
@endsection

@push('scripts')
<script>
// Автоматическое обновление статуса платежа для неоплаченных заказов
@if($order->status === 'pending' && $order->yookassa_payment_id)
let paymentStatusInterval = null;

function checkPaymentStatus() {
    fetch('{{ route("payment.status", $order->yookassa_payment_id) }}', {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        },
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'succeeded' || data.paid || data.order_status === 'paid') {
            // Платеж успешен, перезагружаем страницу для обновления статуса
            window.location.reload();
        } else if (data.status === 'canceled') {
            // Платеж отменен
            const badge = document.getElementById('order-status-badge');
            if (badge) {
                badge.className = badge.className.replace('warning', 'error');
                badge.textContent = 'Отменен';
            }
            if (paymentStatusInterval) {
                clearInterval(paymentStatusInterval);
                paymentStatusInterval = null;
            }
        }
        // Если статус pending или waiting_for_capture, продолжаем проверку
    })
    .catch(error => {
        console.error('Error checking payment status:', error);
    });
}

// Запускаем проверку статуса каждые 3 секунды
paymentStatusInterval = setInterval(checkPaymentStatus, 3000);

// Проверяем сразу при загрузке
checkPaymentStatus();

// Останавливаем проверку через 5 минут (300 секунд) если платеж не прошел
setTimeout(function() {
    if (paymentStatusInterval) {
        clearInterval(paymentStatusInterval);
        paymentStatusInterval = null;
    }
}, 300000); // 5 минут
@endif
</script>
@endpush


