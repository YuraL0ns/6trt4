@extends('layouts.app')

@section('title', 'Аналитика - Hunter-Photo.Ru')
@section('page-title', 'Аналитика продаж')

@section('content')
    <x-card class="mb-6">
        <form action="{{ route('admin.analytics') }}" method="GET" class="flex items-end space-x-4">
            <div class="flex-1">
                <label class="block text-sm font-medium text-gray-300 mb-2">Период с</label>
                <input type="date" name="start_date" value="{{ $startDate }}" class="w-full px-4 py-2 bg-[#121212] border border-gray-700 rounded-lg text-white">
            </div>
            <div class="flex-1">
                <label class="block text-sm font-medium text-gray-300 mb-2">Период по</label>
                <input type="date" name="end_date" value="{{ $endDate }}" class="w-full px-4 py-2 bg-[#121212] border border-gray-700 rounded-lg text-white">
            </div>
            <x-button type="submit">Применить</x-button>
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


