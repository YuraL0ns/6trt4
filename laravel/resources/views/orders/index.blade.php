@extends('layouts.app')

@section('title', 'Мои заказы - Hunter-Photo.Ru')
@section('page-title', 'Мои заказы')

@php
    use Illuminate\Support\Facades\Storage;
@endphp

@section('content')
    @if($orders->count() > 0)
        <div class="space-y-4">
            @foreach($orders as $order)
                <x-card>
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <p class="font-semibold text-white">Заказ #{{ substr($order->id, 0, 8) }}</p>
                            <p class="text-sm text-gray-400">{{ $order->created_at->format('d F Y, H:i') }}</p>
                        </div>
                        <div class="text-right">
                            <x-badge variant="{{ $order->status === 'paid' ? 'success' : 'warning' }}">
                                {{ $order->status === 'paid' ? 'Оплачен' : 'Ожидает оплаты' }}
                            </x-badge>
                            <p class="text-lg font-bold text-[#a78bfa] mt-2">{{ number_format($order->total_amount, 0, ',', ' ') }} ₽</p>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                        @foreach($order->items->take(4) as $item)
                            <div class="aspect-square bg-gray-800 rounded-lg overflow-hidden">
                                <img src="{{ $item->photo->s3_custom_url ?? Storage::url($item->photo->custom_path) }}" alt="Photo" class="w-full h-full object-cover">
                            </div>
                        @endforeach
                    </div>
                    
                    @if($order->items->count() > 4)
                        <p class="text-sm text-gray-400 mb-4">И еще {{ $order->items->count() - 4 }} фотографий</p>
                    @endif
                    
                    <div class="flex space-x-3">
                        <x-button href="{{ route('orders.show', $order->id) }}" variant="outline" size="sm">
                            Подробнее
                        </x-button>
                        @if($order->status === 'paid' && $order->zip_path)
                            <x-button href="{{ Storage::url($order->zip_path) }}" size="sm">
                                Скачать архив
                            </x-button>
                        @endif
                    </div>
                </x-card>
            @endforeach
        </div>

        <div class="mt-6">
            {{ $orders->links() }}
        </div>
    @else
        <x-empty-state 
            title="Нет заказов" 
            description="У вас пока нет заказов"
        >
            <x-slot:action>
                <x-button href="{{ route('events.index') }}">Посмотреть события</x-button>
            </x-slot:action>
        </x-empty-state>
    @endif
@endsection
