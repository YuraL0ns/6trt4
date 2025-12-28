@extends('layouts.app')

@section('title', 'Оповещения - Hunter-Photo.Ru')
@section('page-title', 'Оповещения')

@section('content')
    <div class="mb-6 flex justify-between items-center">
        <div>
            <h2 class="text-xl font-bold text-white">Все оповещения</h2>
            <p class="text-sm text-gray-400 mt-1">События и действия в системе</p>
        </div>
        @php
            $unreadCount = $notifications->whereNull('read_at')->count();
        @endphp
        @if($unreadCount > 0)
            <form action="{{ route('notifications.read-all') }}" method="POST">
                @csrf
                <x-button type="submit" variant="outline">
                    Отметить все как прочитанные ({{ $unreadCount }})
                </x-button>
            </form>
        @endif
    </div>

    @if($notifications->count() > 0)
        <x-card>
            <x-table :headers="['Событие', 'Действие']">
                @foreach($notifications as $notification)
                    <tr class="hover:bg-[#1e1e1e] transition-colors {{ $notification->read_at ? '' : 'bg-gray-900/50' }}">
                        <td class="px-6 py-4">
                            <div class="flex items-start space-x-3">
                                <div class="flex-shrink-0">
                                    @if(!$notification->read_at)
                                        <div class="w-2 h-2 bg-[#a78bfa] rounded-full mt-2"></div>
                                    @endif
                                </div>
                                <div class="flex-1">
                                    <p class="text-sm font-medium text-white">{{ $notification->title }}</p>
                                    <p class="text-sm text-gray-400 mt-1">{{ $notification->message }}</p>
                                    <p class="text-xs text-gray-500 mt-1">{{ $notification->created_at->format('d.m.Y H:i') }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @if($notification->action_url)
                                <x-button href="{{ $notification->action_url }}" size="sm" variant="outline">
                                    Посмотреть
                                </x-button>
                            @else
                                <span class="text-sm text-gray-400">-</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-table>

            <div class="mt-6">
                {{ $notifications->links() }}
            </div>
        </x-card>
    @else
        <x-empty-state 
            title="Нет оповещений" 
            description="У вас пока нет оповещений"
        />
    @endif
@endsection

