@extends('layouts.app')

@section('title', 'Поиск заказов - Hunter-Photo.Ru')
@section('page-title', 'Поиск заказов')

@php
    use Illuminate\Support\Facades\Storage;
@endphp

@section('content')
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div>
            <x-card title="Поиск заказов">
                <form action="{{ route('orders.search') }}" method="GET" class="space-y-4">
                    <x-input label="Email" name="email" type="email" placeholder="email@example.com" />
                    <x-input label="Телефон" name="phone" type="tel" placeholder="+7 (999) 999-99-99" />
                    <p class="text-xs text-gray-400">Укажите email или телефон</p>
                    <x-button type="submit" class="w-full">Найти</x-button>
                </form>
            </x-card>
        </div>

        <div class="lg:col-span-2">
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
                            
                            <div class="grid grid-cols-4 gap-4 mb-4">
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
            @else
                <x-empty-state 
                    title="Заказы не найдены" 
                    description="Попробуйте другой email или телефон"
                />
            @endif
        </div>
    </div>
@endsection


