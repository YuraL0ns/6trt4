@extends('layouts.app')

@section('title', 'Панель управления - Hunter-Photo.Ru')
@section('page-title', 'Панель управления')

@section('content')
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <x-card>
            <p class="text-sm text-gray-400 mb-1">Всего пользователей</p>
            <p class="text-3xl font-bold text-white">{{ number_format($stats['total_users'], 0, ',', ' ') }}</p>
        </x-card>
        
        <x-card>
            <p class="text-sm text-gray-400 mb-1">Фотографов</p>
            <p class="text-3xl font-bold text-[#a78bfa]">{{ number_format($stats['total_photographers'], 0, ',', ' ') }}</p>
        </x-card>
        
        <x-card>
            <p class="text-sm text-gray-400 mb-1">Событий</p>
            <p class="text-3xl font-bold text-white">{{ number_format($stats['total_events'], 0, ',', ' ') }}</p>
        </x-card>
        
        <x-card>
            <p class="text-sm text-gray-400 mb-1">Общая выручка</p>
            <p class="text-3xl font-bold text-[#a78bfa]">{{ number_format($stats['total_revenue'], 0, ',', ' ') }} ₽</p>
        </x-card>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        @if($recentNotifications->count() > 0)
            <x-card title="Оповещения" class="lg:col-span-2">
                <div class="space-y-3">
                    @foreach($recentNotifications as $notification)
                        <div class="flex items-start space-x-3 p-3 bg-[#121212] rounded-lg {{ !$notification->read_at ? 'border-l-4 border-[#a78bfa]' : '' }}">
                            <div class="flex-1">
                                <p class="font-semibold text-white">{{ $notification->title }}</p>
                                <p class="text-sm text-gray-400 mt-1">{{ $notification->message }}</p>
                                <p class="text-xs text-gray-500 mt-1">{{ $notification->created_at->format('d.m.Y H:i') }}</p>
                            </div>
                            <div class="flex items-center space-x-2">
                                @if($notification->action_url)
                                    <x-button href="{{ $notification->action_url }}" size="sm" variant="outline">
                                        Посмотреть
                                    </x-button>
                                @endif
                                <form action="{{ route('admin.notifications.read', $notification->id) }}" method="POST">
                                    @csrf
                                    <x-button type="submit" size="sm" variant="outline">
                                        Отметить
                                    </x-button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="mt-4">
                    <x-button href="{{ route('admin.notifications') }}" variant="outline" class="w-full">
                        Все оповещения
                    </x-button>
                </div>
            </x-card>
        @endif
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <x-card title="Последние заказы">
            @if($recentOrders->count() > 0)
                <div class="space-y-4">
                    @foreach($recentOrders as $order)
                        <div class="flex items-center justify-between p-4 bg-[#121212] rounded-lg">
                            <div>
                                <p class="font-semibold text-white">Заказ #{{ substr($order->id, 0, 8) }}</p>
                                <p class="text-sm text-gray-400">{{ $order->user->full_name ?? $order->email }}</p>
                            </div>
                            <div class="text-right">
                                <p class="font-semibold text-[#a78bfa]">{{ number_format($order->total_amount, 0, ',', ' ') }} ₽</p>
                                <x-badge variant="{{ $order->status === 'paid' ? 'success' : 'warning' }}" size="sm">
                                    {{ $order->status === 'paid' ? 'Оплачен' : 'Ожидает' }}
                                </x-badge>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-gray-400 text-center py-8">Нет заказов</p>
            @endif
        </x-card>

        <x-card title="Быстрые действия">
            <div class="space-y-3">
                <x-button href="{{ route('admin.users.index') }}" variant="outline" class="w-full justify-start">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                    </svg>
                    Управление пользователями
                </x-button>
                <x-button href="{{ route('admin.events.index') }}" variant="outline" class="w-full justify-start">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                    Все события
                </x-button>
                <x-button href="{{ route('admin.withdrawals.index') }}" variant="outline" class="w-full justify-start">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                    Заявки на вывод
                </x-button>
                <x-button href="{{ route('admin.settings.index') }}" variant="outline" class="w-full justify-start">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    </svg>
                    Настройки
                </x-button>
            </div>
        </x-card>
    </div>
@endsection


