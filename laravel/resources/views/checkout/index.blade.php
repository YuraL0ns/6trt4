@extends('layouts.app')

@section('title', 'Оформление заказа - Hunter-Photo.Ru')
@section('page-title', 'Оформление заказа')

@php
    use Illuminate\Support\Facades\Storage;
@endphp

@section('content')
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2">
            <x-card title="Данные для заказа">
                <form action="{{ route('checkout.store') }}" method="POST" class="space-y-4">
                    @csrf
                    
                    @auth
                        <x-input label="Email" name="email" type="email" value="{{ auth()->user()->email }}" required readonly />
                        @if(auth()->user()->phone)
                            <input type="hidden" name="phone" value="{{ auth()->user()->phone }}">
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-2">Телефон</label>
                                <div class="px-4 py-2 bg-[#1e1e1e] border border-gray-700 rounded-lg text-gray-400">
                                    {{ auth()->user()->phone }}
                                </div>
                                <p class="text-xs text-gray-500 mt-1">Телефон взят из вашего профиля</p>
                            </div>
                        @else
                            <x-input label="Телефон" name="phone" type="tel" placeholder="+7 (999) 999-99-99" value="{{ old('phone') }}" />
                        @endif
                    @else
                        <x-input label="Email" name="email" type="email" placeholder="email@example.com" value="{{ old('email') }}" required />
                        <x-input label="Телефон" name="phone" type="tel" placeholder="+7 (999) 999-99-99" value="{{ old('phone') }}" />
                        <x-alert type="info">
                            После оплаты вы сможете найти свой заказ, указав email или телефон на странице поиска заказов
                        </x-alert>
                    @endauth
                    
                    @if($errors->any())
                        <x-alert type="error">
                            <ul class="list-disc list-inside">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </x-alert>
                    @endif
                    
                    <x-button type="submit" class="w-full" size="lg">
                        Перейти к оплате
                    </x-button>
                </form>
            </x-card>
        </div>

        <div>
            <x-card title="Ваш заказ">
                <div class="space-y-4">
                    <div>
                        <p class="text-sm text-gray-400 mb-2">Товаров в заказе:</p>
                        <p class="text-lg font-semibold text-white">{{ $cartItems->count() }}</p>
                    </div>
                    
                    <div class="space-y-2 max-h-64 overflow-y-auto">
                        @foreach($cartItems as $item)
                            <div class="flex items-center space-x-3 p-2 bg-[#121212] rounded-lg">
                                <div class="w-16 h-16 bg-gray-800 rounded-lg overflow-hidden flex-shrink-0">
                                    <img src="{{ $item->photo->s3_custom_url ?? Storage::url($item->photo->custom_path) }}" alt="Photo" class="w-full h-full object-cover">
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm text-white truncate">{{ $item->photo->event->title }}</p>
                                    <p class="text-sm font-semibold text-[#a78bfa]">{{ number_format($item->photo->getPriceWithCommission(), 0, ',', ' ') }} ₽</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    
                    <div class="border-t border-gray-800 pt-4">
                        <div class="flex justify-between mb-2">
                            <span class="text-gray-400">Итого:</span>
                            <span class="text-2xl font-bold text-[#a78bfa]">{{ number_format($total, 0, ',', ' ') }} ₽</span>
                        </div>
                    </div>
                </div>
            </x-card>
        </div>
    </div>
@endsection


