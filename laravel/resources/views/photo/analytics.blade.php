@extends('layouts.app')

@section('title', 'Аналитика - Hunter-Photo.Ru')
@section('page-title', 'Аналитика продаж')

@section('content')
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <x-card>
            <p class="text-sm text-gray-400 mb-1">Всего продано фотографий</p>
            <p class="text-3xl font-bold text-white">{{ number_format($totalPhotosSold, 0, ',', ' ') }}</p>
        </x-card>
        
        <x-card>
            <p class="text-sm text-gray-400 mb-1">Общий заработок</p>
            <p class="text-3xl font-bold text-[#a78bfa]">{{ number_format($totalEarnings, 0, ',', ' ') }} ₽</p>
        </x-card>
        
        <x-card>
            <p class="text-sm text-gray-400 mb-1">Текущий баланс</p>
            <p class="text-3xl font-bold text-white">{{ number_format(auth()->user()->balance, 0, ',', ' ') }} ₽</p>
        </x-card>
    </div>

    <x-card title="Статистика по событиям">
        @if(count($analytics) > 0)
            <x-table :headers="['Событие', 'Город', 'Дата', 'Продано фотографий', 'Заработок', 'Действия']">
                @foreach($analytics as $item)
                    <tr class="hover:bg-[#1e1e1e] transition-colors">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-white">{{ $item['event']->title }}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">{{ $item['event']->city }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">{{ $item['event']->date->format('d.m.Y') }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-white font-semibold">{{ $item['photos_sold'] }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-[#a78bfa] font-semibold">{{ number_format($item['earnings'], 0, ',', ' ') }} ₽</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            @if($item['photos_sold'] > 0)
                                <a href="{{ route('photo.analytics.download-purchased-photos', ['eventId' => $item['event']->id]) }}" 
                                   class="inline-flex items-center px-3 py-2 bg-[#a78bfa] hover:bg-[#8b6cf7] text-white rounded-lg transition-colors text-sm">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                                    </svg>
                                    Скачать купленные фото
                                </a>
                            @else
                                <span class="text-gray-500 text-sm">Нет продаж</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-table>
        @else
            <x-empty-state 
                title="Нет данных" 
                description="У вас пока нет продаж"
            />
        @endif
    </x-card>

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
            <x-empty-state 
                title="Нет купленных фотографий" 
                description="Пока нет фотографий, которые были куплены"
            />
        @endif
    </x-card>
@endsection


