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
            <x-table :headers="['Событие', 'Город', 'Дата', 'Продано фотографий', 'Заработок']">
                @foreach($analytics as $item)
                    <tr class="hover:bg-[#1e1e1e] transition-colors">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-white">{{ $item['event']->title }}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">{{ $item['event']->city }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">{{ $item['event']->date->format('d.m.Y') }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-white font-semibold">{{ $item['photos_sold'] }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-[#a78bfa] font-semibold">{{ number_format($item['earnings'], 0, ',', ' ') }} ₽</td>
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
@endsection


