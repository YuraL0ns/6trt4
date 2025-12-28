@php
    use Illuminate\Support\Facades\Storage;
@endphp

@extends('layouts.app')

@section('title', 'Профиль пользователя - Hunter-Photo.Ru')
@section('page-title', 'Профиль пользователя')

@section('content')
    <div class="mb-6">
        <x-button href="{{ route('admin.users.index') }}" variant="outline">
            ← Назад к списку
        </x-button>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Информация о пользователе -->
        <div class="lg:col-span-2">
            <x-card title="Информация о пользователе">
                <div class="space-y-4">
                    <div class="flex items-center space-x-4 mb-6">
                        @if($user->avatar)
                            <img src="{{ Storage::url($user->avatar) }}" alt="Аватар" class="w-24 h-24 rounded-full object-cover">
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
                            <div class="flex space-x-2 mt-2">
                                <x-badge variant="default">
                                    @if($user->group === 'admin') Администратор
                                    @elseif($user->group === 'photo') Фотограф
                                    @elseif($user->group === 'blocked') Заблокирован
                                    @else Пользователь
                                    @endif
                                </x-badge>
                                <x-badge variant="{{ $user->status === 'active' ? 'success' : 'error' }}">
                                    {{ $user->status === 'active' ? 'Активен' : 'Заблокирован' }}
                                </x-badge>
                            </div>
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
                        <div>
                            <p class="text-sm text-gray-400 mb-1">Баланс</p>
                            <p class="text-white font-semibold">{{ number_format($user->balance, 2, ',', ' ') }} ₽</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-400 mb-1">Дата регистрации</p>
                            <p class="text-white font-semibold">{{ $user->created_at->format('d.m.Y H:i') }}</p>
                        </div>
                    </div>

                    @if($user->isPhotographer() && $user->description)
                    <div class="pt-4 border-t border-gray-700">
                        <p class="text-sm text-gray-400 mb-2">Описание</p>
                        <p class="text-white">{{ $user->description }}</p>
                    </div>
                    @endif
                </div>
            </x-card>
        </div>

        <!-- Статистика -->
        <div class="lg:col-span-1">
            <x-card title="Статистика">
                @if($user->isUser() || $user->isPhotographer())
                    <div class="space-y-4">
                        <div>
                            <p class="text-sm text-gray-400 mb-1">Количество заказов</p>
                            <p class="text-2xl font-bold text-white">{{ $userStats['orders_count'] }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-400 mb-1">Всего потрачено</p>
                            <p class="text-2xl font-bold text-white">{{ number_format($userStats['total_spent'], 2, ',', ' ') }} ₽</p>
                        </div>
                    </div>
                @endif

                @if($user->isPhotographer() && $photographerStats)
                    <div class="space-y-4 mt-6 pt-6 border-t border-gray-700">
                        <div>
                            <p class="text-sm text-gray-400 mb-1">Событий создано</p>
                            <p class="text-2xl font-bold text-white">{{ $photographerStats['events_count'] }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-400 mb-1">Текущий баланс</p>
                            <p class="text-2xl font-bold text-white">{{ number_format($photographerStats['balance'], 2, ',', ' ') }} ₽</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-400 mb-1">Заявок на вывод</p>
                            <p class="text-2xl font-bold text-white">{{ $photographerStats['withdrawals_count'] }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-400 mb-1">Всего заработано</p>
                            <p class="text-2xl font-bold text-white">{{ number_format($photographerStats['total_earned'], 2, ',', ' ') }} ₽</p>
                        </div>
                    </div>
                @endif
            </x-card>
        </div>
    </div>

    @if($user->isPhotographer() && $user->events->count() > 0)
    <div class="mt-6">
        <x-card title="События фотографа">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($user->events->take(6) as $event)
                    <div class="border border-gray-700 rounded-lg p-4">
                        <h4 class="text-white font-semibold mb-2">{{ $event->title }}</h4>
                        <p class="text-sm text-gray-400 mb-1">{{ $event->city }}</p>
                        <p class="text-sm text-gray-400 mb-2">{{ $event->date->format('d.m.Y') }}</p>
                        <x-badge variant="{{ $event->status === 'published' ? 'success' : 'default' }}">
                            {{ $event->status === 'published' ? 'Опубликовано' : 'Черновик' }}
                        </x-badge>
                    </div>
                @endforeach
            </div>
        </x-card>
    </div>
    @endif

    @if($user->orders->count() > 0)
    <div class="mt-6">
        <x-card title="Последние заказы">
            <div class="space-y-4">
                @foreach($user->orders->take(5) as $order)
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
                    </div>
                @endforeach
            </div>
        </x-card>
    </div>
    @endif
@endsection

