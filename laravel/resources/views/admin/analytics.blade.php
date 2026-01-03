@extends('layouts.app')

@section('title', 'Аналитика - Hunter-Photo.Ru')
@section('page-title', 'Аналитика продаж')

@section('content')
    <x-card class="mb-6">
        <form action="{{ route('admin.analytics') }}" method="GET" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Период с</label>
                    <input type="date" name="start_date" value="{{ $startDate }}" class="w-full px-4 py-2 bg-[#121212] border border-gray-700 rounded-lg text-white">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Период по</label>
                    <input type="date" name="end_date" value="{{ $endDate }}" class="w-full px-4 py-2 bg-[#121212] border border-gray-700 rounded-lg text-white">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Email</label>
                    <input type="text" name="search_email" value="{{ request('search_email') }}" placeholder="Поиск по email" class="w-full px-4 py-2 bg-[#121212] border border-gray-700 rounded-lg text-white">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Телефон</label>
                    <input type="text" name="search_phone" value="{{ request('search_phone') }}" placeholder="Поиск по телефону" class="w-full px-4 py-2 bg-[#121212] border border-gray-700 rounded-lg text-white">
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Название события</label>
                    <input type="text" name="search_event" value="{{ request('search_event') }}" placeholder="Поиск по названию события" class="w-full px-4 py-2 bg-[#121212] border border-gray-700 rounded-lg text-white">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Дата заказа</label>
                    <input type="date" name="search_date" value="{{ request('search_date') }}" class="w-full px-4 py-2 bg-[#121212] border border-gray-700 rounded-lg text-white">
                </div>
            </div>
            <div class="flex space-x-2">
                <x-button type="submit">Применить фильтры</x-button>
                <x-button type="button" variant="outline" onclick="window.location.href='{{ route('admin.analytics') }}'">Сбросить</x-button>
            </div>
        </form>
    </x-card>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <x-card>
            <p class="text-sm text-gray-400 mb-1">Общая выручка</p>
            <p class="text-3xl font-bold text-white">{{ number_format($totalRevenue, 0, ',', ' ') }} ₽</p>
        </x-card>
        
        <x-card>
            <p class="text-sm text-gray-400 mb-1">Всего заказов</p>
            <p class="text-3xl font-bold text-[#a78bfa]">{{ number_format($totalOrders, 0, ',', ' ') }}</p>
        </x-card>
        
        <x-card>
            <p class="text-sm text-gray-400 mb-1">Комиссия платформы</p>
            <p class="text-3xl font-bold text-white">{{ number_format($platformCommission, 0, ',', ' ') }} ₽</p>
        </x-card>
        
        <x-card>
            <p class="text-sm text-gray-400 mb-1">Заработок фотографов</p>
            <p class="text-3xl font-bold text-[#a78bfa]">{{ number_format($photographersEarnings, 0, ',', ' ') }} ₽</p>
        </x-card>
    </div>

    <!-- КРИТИЧЕСКОЕ ИСПРАВЛЕНИЕ: Список продаж с поиском -->
    <x-card title="Список продаж" class="mb-6">
        @if($orders->count() > 0)
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-700">
                            <th class="text-left py-3 px-4 text-sm font-semibold text-gray-300">ID заказа</th>
                            <th class="text-left py-3 px-4 text-sm font-semibold text-gray-300">Дата</th>
                            <th class="text-left py-3 px-4 text-sm font-semibold text-gray-300">Email</th>
                            <th class="text-left py-3 px-4 text-sm font-semibold text-gray-300">Телефон</th>
                            <th class="text-left py-3 px-4 text-sm font-semibold text-gray-300">События</th>
                            <th class="text-right py-3 px-4 text-sm font-semibold text-gray-300">Сумма</th>
                            <th class="text-center py-3 px-4 text-sm font-semibold text-gray-300">Фото</th>
                            <th class="text-center py-3 px-4 text-sm font-semibold text-gray-300">Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($orders as $order)
                            @php
                                // Получаем уникальные события из заказа
                                $events = $order->items->pluck('photo.event')->filter()->unique('id')->values();
                            @endphp
                            <tr class="border-b border-gray-800 hover:bg-gray-800/50">
                                <td class="py-3 px-4">
                                    <a href="{{ route('orders.show', $order->id) }}" class="text-[#a78bfa] hover:text-[#8b5cf6]">
                                        #{{ substr($order->id, 0, 8) }}
                                    </a>
                                </td>
                                <td class="py-3 px-4 text-gray-300">
                                    {{ $order->created_at->format('d.m.Y H:i') }}
                                </td>
                                <td class="py-3 px-4 text-gray-300">
                                    {{ $order->email }}
                                </td>
                                <td class="py-3 px-4 text-gray-300">
                                    {{ $order->phone ?? '-' }}
                                </td>
                                <td class="py-3 px-4 text-gray-300">
                                    @if($events->count() > 0)
                                        <div class="space-y-1">
                                            @foreach($events->take(2) as $event)
                                                <div class="text-sm">{{ $event->title }}</div>
                                            @endforeach
                                            @if($events->count() > 2)
                                                <div class="text-xs text-gray-500">+{{ $events->count() - 2 }} еще</div>
                                            @endif
                                        </div>
                                    @else
                                        <span class="text-gray-500">-</span>
                                    @endif
                                </td>
                                <td class="py-3 px-4 text-right text-white font-semibold">
                                    {{ number_format($order->total_amount, 0, ',', ' ') }} ₽
                                </td>
                                <td class="py-3 px-4 text-center text-gray-300">
                                    {{ $order->items->count() }}
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <a href="{{ route('orders.show', $order->id) }}" class="text-[#a78bfa] hover:text-[#8b5cf6] text-sm">
                                        Просмотр
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            
            <div class="mt-6">
                {{ $orders->links('vendor.pagination.default') }}
            </div>
        @else
            <p class="text-gray-400 text-center py-8">Продажи не найдены</p>
        @endif
    </x-card>

    <x-card title="Статистика по месяцам">
        @if($monthlyStats->count() > 0)
            <!-- График -->
            <div class="mb-8">
                <canvas id="revenueChart" class="w-full" style="max-height: 400px;"></canvas>
            </div>
            
            <!-- Таблица -->
            <div class="space-y-4">
                @foreach($monthlyStats as $stat)
                    <div class="flex items-center justify-between p-4 bg-[#121212] rounded-lg">
                        <div>
                            <p class="font-semibold text-white">{{ \Carbon\Carbon::parse($stat->month)->format('F Y') }}</p>
                            <p class="text-sm text-gray-400">{{ $stat->orders_count }} заказов</p>
                        </div>
                        <p class="text-lg font-bold text-[#a78bfa]">{{ number_format($stat->revenue, 0, ',', ' ') }} ₽</p>
                    </div>
                @endforeach
            </div>
        @else
            <p class="text-gray-400 text-center py-8">Нет данных за выбранный период</p>
        @endif
    </x-card>

    <!-- Таблица купленных фотографий -->
    <x-card title="Купленные фотографии" class="mt-6">
        @if(isset($purchasedPhotos) && count($purchasedPhotos) > 0)
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-700">
                            <th class="text-left py-3 px-4 text-sm font-semibold text-gray-300">UUID</th>
                            <th class="text-left py-3 px-4 text-sm font-semibold text-gray-300">Событие</th>
                            <th class="text-center py-3 px-4 text-sm font-semibold text-gray-300">Количество продаж</th>
                            <th class="text-right py-3 px-4 text-sm font-semibold text-gray-300">Выручка</th>
                            <th class="text-center py-3 px-4 text-sm font-semibold text-gray-300">Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($purchasedPhotos as $photo)
                            <tr class="border-b border-gray-800 hover:bg-gray-800/50">
                                <td class="py-3 px-4">
                                    <code class="text-xs text-gray-400 font-mono">{{ substr($photo['uuid'], 0, 8) }}...</code>
                                </td>
                                <td class="py-3 px-4 text-gray-300">
                                    <div class="text-sm">{{ $photo['event']->title ?? 'Неизвестно' }}</div>
                                    @if($photo['event'])
                                        <div class="text-xs text-gray-500">{{ $photo['event']->city ?? '' }}</div>
                                    @endif
                                </td>
                                <td class="py-3 px-4 text-center text-white font-semibold">
                                    {{ $photo['sales_count'] }}
                                </td>
                                <td class="py-3 px-4 text-right text-[#a78bfa] font-semibold">
                                    {{ number_format($photo['total_revenue'], 0, ',', ' ') }} ₽
                                </td>
                                <td class="py-3 px-4 text-center">
                                    @if($photo['event'])
                                        <a href="{{ route('events.show', $photo['event_slug']) }}#photo-{{ $photo['uuid'] }}" 
                                           target="_blank"
                                           class="text-[#a78bfa] hover:text-[#8b5cf6] text-sm">
                                            Открыть фото
                                        </a>
                                    @else
                                        <span class="text-gray-500 text-sm">-</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-gray-400 text-center py-8">Купленные фотографии не найдены</p>
        @endif
    </x-card>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
    @if($monthlyStats->count() > 0)
    const ctx = document.getElementById('revenueChart');
    const monthlyData = @json($monthlyStats->map(function($stat) {
        return [
            \Carbon\Carbon::parse($stat->month)->format('M Y'),
            (float)$stat->revenue,
            (int)$stat->orders_count
        ];
    }));

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: monthlyData.map(item => item[0]),
            datasets: [{
                label: 'Выручка (₽)',
                data: monthlyData.map(item => item[1]),
                borderColor: '#a78bfa',
                backgroundColor: 'rgba(167, 139, 250, 0.1)',
                tension: 0.4,
                fill: true,
                yAxisID: 'y',
            }, {
                label: 'Количество заказов',
                data: monthlyData.map(item => item[2]),
                borderColor: '#8b5cf6',
                backgroundColor: 'rgba(139, 92, 246, 0.1)',
                tension: 0.4,
                fill: true,
                yAxisID: 'y1',
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    labels: {
                        color: '#ffffff'
                    }
                },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                }
            },
            scales: {
                x: {
                    ticks: {
                        color: '#9ca3af'
                    },
                    grid: {
                        color: '#374151'
                    }
                },
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    ticks: {
                        color: '#a78bfa',
                        callback: function(value) {
                            return new Intl.NumberFormat('ru-RU').format(value) + ' ₽';
                        }
                    },
                    grid: {
                        color: '#374151'
                    }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    ticks: {
                        color: '#8b5cf6'
                    },
                    grid: {
                        drawOnChartArea: false,
                    },
                }
            },
            interaction: {
                mode: 'nearest',
                axis: 'x',
                intersect: false
            }
        }
    });
    @endif
</script>
@endpush


