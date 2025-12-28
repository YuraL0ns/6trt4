@extends('layouts.app')

@section('title', 'Корзина - Hunter-Photo.Ru')
@section('page-title', 'Корзина')

@php
    use Illuminate\Support\Facades\Storage;
@endphp

@section('content')
    @if($cartItems->count() > 0)
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2">
                <x-card>
                    <div class="space-y-4">
                        @foreach($cartItems as $item)
                            <div class="flex items-center space-x-4 p-4 bg-[#121212] rounded-lg">
                                <div class="w-24 h-24 bg-gray-800 rounded-lg overflow-hidden flex-shrink-0">
                                    <img src="{{ $item->photo->s3_custom_url ?? Storage::url($item->photo->custom_path) }}" alt="Photo" class="w-full h-full object-cover">
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="font-semibold text-white truncate">{{ $item->photo->event->title }}</p>
                                    <p class="text-sm text-gray-400">{{ $item->photo->event->city }}</p>
                                    <p class="text-lg font-bold text-[#a78bfa] mt-2">{{ number_format($item->photo->getPriceWithCommission(), 0, ',', ' ') }} ₽</p>
                                </div>
                                <form action="{{ route('cart.destroy', $item->id) }}" method="POST">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-500 hover:text-red-400">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                        </svg>
                                    </button>
                                </form>
                            </div>
                        @endforeach
                    </div>
                </x-card>
            </div>

            <div>
                <x-card title="Итого">
                    <div class="space-y-4">
                        <div class="flex justify-between">
                            <span class="text-gray-400">Товаров:</span>
                            <span class="text-white font-semibold">{{ $cartItems->count() }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-400">Сумма:</span>
                            <span class="text-white font-semibold text-xl">{{ number_format($total, 0, ',', ' ') }} ₽</span>
                        </div>
                        <x-button href="{{ route('checkout.index') }}" class="w-full" size="lg">
                            Перейти к оплате
                        </x-button>
                    </div>
                </x-card>
            </div>
        </div>
    @else
        <x-empty-state 
            title="Корзина пуста" 
            description="Добавьте фотографии в корзину для покупки"
        >
            <x-slot:action>
                <x-button href="{{ route('events.index') }}">Посмотреть события</x-button>
            </x-slot:action>
        </x-empty-state>
    @endif
@endsection
