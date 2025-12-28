@extends('layouts.app')

@section('title', 'Заявки на смену группы - Hunter-Photo.Ru')
@section('page-title', 'Заявки на смену группы')

@php
    use Illuminate\Support\Str;
@endphp

@section('content')
    @if($requests->count() > 0)
        <x-table :headers="['Пользователь', 'Текущая группа', 'Запрашиваемая группа', 'Причина', 'Дата', 'Действия']">
            @foreach($requests as $request)
                <tr class="hover:bg-[#1e1e1e] transition-colors">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm font-medium text-white">{{ $request->user->full_name }}</div>
                        <div class="text-sm text-gray-400">{{ $request->user->email }}</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <x-badge variant="default" size="sm">
                            @if($request->grp_now === 'admin') Администратор
                            @elseif($request->grp_now === 'photo') Фотограф
                            @else Пользователь
                            @endif
                        </x-badge>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <x-badge variant="info" size="sm">
                            @if($request->grp_chg === 'admin') Администратор
                            @elseif($request->grp_chg === 'photo') Фотограф
                            @else Пользователь
                            @endif
                        </x-badge>
                    </td>
                    <td class="px-6 py-4">
                        <p class="text-sm text-gray-300 line-clamp-2">{{ Str::limit($request->reason, 100) }}</p>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-400">
                        {{ $request->created_at->format('d.m.Y H:i') }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                        <div class="flex space-x-2">
                            <x-button href="{{ route('admin.group-requests.approve', $request->id) }}" size="sm" variant="success">
                                Одобрить
                            </x-button>
                            <x-button href="{{ route('admin.group-requests.reject', $request->id) }}" size="sm" variant="danger">
                                Отклонить
                            </x-button>
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-table>

        <div class="mt-6">
            {{ $requests->links() }}
        </div>
    @else
        <x-empty-state 
            title="Нет заявок" 
            description="Нет активных заявок на смену группы"
        />
    @endif
@endsection

