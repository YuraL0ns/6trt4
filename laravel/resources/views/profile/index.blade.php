@extends('layouts.app')

@php
    use Illuminate\Support\Facades\Storage;
@endphp

@section('title', 'Профиль - Hunter-Photo.Ru')
@section('page-title', 'Мой профиль')

@section('content')
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Информация о пользователе -->
        <div class="lg:col-span-2">
            <x-card title="Информация о пользователе">
                <div class="space-y-4">
                    <div class="flex items-center space-x-4 mb-6">
                        @if($user->avatar && Storage::exists($user->avatar))
                            <img src="{{ Storage::url($user->avatar) }}" alt="Аватар" class="w-24 h-24 rounded-full object-cover">
                        @elseif($user->avatar)
                            <img src="{{ asset('storage/' . $user->avatar) }}" alt="Аватар" class="w-24 h-24 rounded-full object-cover" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                            <div class="w-24 h-24 rounded-full bg-gradient-to-br from-[#a78bfa] to-[#8b5cf6] flex items-center justify-center text-white text-2xl font-bold" style="display: none;">
                                {{ mb_substr($user->last_name, 0, 1) }}{{ mb_substr($user->first_name, 0, 1) }}
                            </div>
                        @else
                            <div class="w-24 h-24 rounded-full bg-gradient-to-br from-[#a78bfa] to-[#8b5cf6] flex items-center justify-center text-white text-2xl font-bold">
                                {{ mb_substr($user->last_name, 0, 1) }}{{ mb_substr($user->first_name, 0, 1) }}
                            </div>
                        @endif
                        <div>
                            <h3 class="text-xl font-bold text-white">{{ $user->full_name }}</h3>
                            <p class="text-gray-400">{{ $user->email }}</p>
                            @if($user->login)
                                <p class="text-gray-400">Логин: {{ $user->login }}</p>
                            @endif
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm text-gray-400 mb-1">Имя</p>
                            <p class="text-white font-semibold">{{ $user->first_name }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-400 mb-1">Фамилия</p>
                            <p class="text-white font-semibold">{{ $user->last_name }}</p>
                        </div>
                        @if($user->second_name)
                        <div>
                            <p class="text-sm text-gray-400 mb-1">Отчество</p>
                            <p class="text-white font-semibold">{{ $user->second_name }}</p>
                        </div>
                        @endif
                        <div>
                            <p class="text-sm text-gray-400 mb-1">Email</p>
                            <p class="text-white font-semibold">{{ $user->email }}</p>
                        </div>
                        @if($user->phone)
                        <div>
                            <p class="text-sm text-gray-400 mb-1">Телефон</p>
                            <p class="text-white font-semibold">{{ $user->phone }}</p>
                        </div>
                        @endif
                        @if($user->city)
                        <div>
                            <p class="text-sm text-gray-400 mb-1">Город</p>
                            <p class="text-white font-semibold">{{ $user->city }}</p>
                        </div>
                        @endif
                        @if($user->gender)
                        <div>
                            <p class="text-sm text-gray-400 mb-1">Пол</p>
                            <p class="text-white font-semibold">{{ $user->gender === 'male' ? 'Мужской' : 'Женский' }}</p>
                        </div>
                        @endif
                    </div>

                    <div class="pt-4 border-t border-gray-700 space-y-3">
                        <x-button href="{{ route('profile.edit') }}" variant="outline" class="w-full">
                            Редактировать профиль
                        </x-button>
                        @if($user->group === 'user')
                            <x-button href="{{ route('profile.settings.photo_me') }}" class="w-full">
                                Стать фотографом
                            </x-button>
                        @endif
                    </div>
                </div>
            </x-card>
        </div>

        <!-- Последние покупки -->
        <div class="lg:col-span-1">
            <x-card title="Последние покупки">
                @if($orders->count() > 0)
                    <div class="space-y-4">
                        @foreach($orders as $order)
                            <div class="border-b border-gray-700 pb-4 last:border-0 last:pb-0">
                                <div class="flex justify-between items-start mb-2">
                                    <div>
                                        <p class="text-white font-semibold">Заказ #{{ substr($order->id, 0, 8) }}</p>
                                        <p class="text-sm text-gray-400">{{ $order->created_at->format('d.m.Y H:i') }}</p>
                                    </div>
                                    <x-badge :variant="$order->status === 'paid' ? 'success' : 'warning'">
                                        {{ $order->status === 'paid' ? 'Оплачен' : 'Ожидает оплаты' }}
                                    </x-badge>
                                </div>
                                <p class="text-white font-semibold">{{ number_format($order->total_amount, 2, ',', ' ') }} ₽</p>
                                <p class="text-sm text-gray-400">Фотографий: {{ $order->items->count() }}</p>
                                <x-button href="{{ route('orders.show', $order->id) }}" size="sm" variant="outline" class="mt-2 w-full">
                                    Подробнее
                                </x-button>
                            </div>
                        @endforeach
                    </div>
                    <div class="mt-4">
                        <x-button href="{{ route('orders.index') }}" variant="outline" class="w-full">
                            Все заказы
                        </x-button>
                    </div>
                @else
                    <p class="text-gray-400 text-center py-4">У вас пока нет покупок</p>
                    <x-button href="{{ route('events.index') }}" variant="outline" class="w-full">
                        Посмотреть события
                    </x-button>
                @endif
            </x-card>
        </div>
    </div>
@endsection

