@extends('layouts.app')

@section('title', 'Все события - Hunter-Photo.Ru')
@section('page-title', 'Все события')

@php
    // Функция для получения перевода статуса и цвета badge
    function getStatusBadge($status) {
        $statuses = [
            'draft' => ['text' => 'Черновик', 'variant' => 'default'],
            'processing' => ['text' => 'В обработке', 'variant' => 'warning'],
            'published' => ['text' => 'Опубликовано', 'variant' => 'success'],
            'completed' => ['text' => 'Завершено', 'variant' => 'info'],
            'archived' => ['text' => 'Архивировано', 'variant' => 'default'],
        ];
        
        return $statuses[$status] ?? ['text' => $status, 'variant' => 'default'];
    }
@endphp

@section('content')
    @if(session('success'))
        <x-alert type="success" class="mb-6">
            {{ session('success') }}
        </x-alert>
    @endif

    <div class="mb-6">
        <a href="{{ route('admin.events.create') }}" class="px-4 py-2 bg-[#a78bfa] hover:bg-[#8b5cf6] text-white rounded-lg transition-colors">
            Создать событие
        </a>
    </div>

    @if(isset($events) && $events->count() > 0)
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-800">
                <thead class="bg-[#1e1e1e]">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase">Название</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase">Город</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase">Дата</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase">Автор</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase">Статус</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase">Действия</th>
                    </tr>
                </thead>
                <tbody class="bg-[#121212] divide-y divide-gray-800">
                    @foreach($events as $event)
                        @php
                            $statusInfo = getStatusBadge($event->status);
                        @endphp
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-white">
                                {{ $event->title ?? 'Без названия' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">
                                {{ $event->city ?? '-' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">
                                {{ $event->getFormattedDate() }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">
                                {{ $event->author->full_name ?? 'Не указан' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <x-badge variant="{{ $statusInfo['variant'] }}">
                                    {{ $statusInfo['text'] }}
                                </x-badge>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <div class="flex space-x-2">
                                    <a 
                                        href="{{ route('events.show', $event->slug) }}"
                                        target="_blank"
                                        class="px-3 py-1 bg-blue-600 hover:bg-blue-700 text-white rounded text-xs transition-colors"
                                    >
                                        Просмотр
                                    </a>
                                    <a 
                                        href="{{ route('admin.events.edit', $event->id) }}"
                                        class="px-3 py-1 bg-[#a78bfa] hover:bg-[#8b5cf6] text-white rounded text-xs transition-colors"
                                    >
                                        Редактировать
                                    </a>
                                    @if($event->status !== 'archived')
                                        <button 
                                            onclick="archiveEvent('{{ $event->id }}')"
                                            class="px-3 py-1 bg-gray-700 hover:bg-gray-600 text-white rounded text-xs transition-colors"
                                        >
                                            Архивировать
                                        </button>
                                    @else
                                        <button 
                                            onclick="unarchiveEvent('{{ $event->id }}')"
                                            class="px-3 py-1 bg-green-600 hover:bg-green-700 text-white rounded text-xs transition-colors"
                                        >
                                            Разархивировать
                                        </button>
                                    @endif
                                    <button 
                                        onclick="showDeleteModal('{{ $event->id }}', '{{ addslashes($event->title) }}')"
                                        class="px-3 py-1 bg-red-600 hover:bg-red-700 text-white rounded text-xs transition-colors"
                                    >
                                        Удалить
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-6">
            {{ $events->links('vendor.pagination.default') }}
        </div>
    @else
        <div class="text-center py-12">
            <p class="text-gray-400">Нет событий</p>
        </div>
    @endif
@endsection

<!-- Модальное окно подтверждения удаления -->
<div id="delete-event-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-[#1a1a1a] rounded-lg p-6 max-w-md w-full mx-4">
        <h3 class="text-lg font-semibold text-white mb-4">Подтверждение удаления</h3>
        <p class="text-gray-300 mb-6">
            Вы действительно хотите удалить событие "<span id="delete-event-title"></span>"? 
            Это действие удалит все связанные данные, включая фотографии и файлы. 
            Это действие нельзя отменить.
        </p>
        <div class="flex space-x-3">
            <form id="delete-event-form" method="POST" class="flex-1">
                @csrf
                @method('DELETE')
                <button type="submit" class="w-full px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded transition-colors">
                    Да, удалить
                </button>
            </form>
            <button onclick="closeDeleteModal()" class="flex-1 px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded transition-colors">
                Отмена
            </button>
        </div>
    </div>
</div>

@push('scripts')
<script>
function showDeleteModal(eventId, eventTitle) {
    document.getElementById('delete-event-title').textContent = eventTitle;
    document.getElementById('delete-event-form').action = `/admin/events/${eventId}`;
    document.getElementById('delete-event-modal').classList.remove('hidden');
}

function closeDeleteModal() {
    document.getElementById('delete-event-modal').classList.add('hidden');
}

// Закрытие модального окна при клике вне его
document.getElementById('delete-event-modal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeDeleteModal();
    }
});

function archiveEvent(eventId) {
    if (!confirm('Вы уверены, что хотите архивировать это событие? Событие будет скрыто со страниц сайта.')) {
        return;
    }
    
    fetch(`/admin/events/${eventId}/archive`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => {
        if (response.ok) {
            location.reload();
        } else {
            alert('Ошибка при архивировании события');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Ошибка при архивировании события');
    });
}

function unarchiveEvent(eventId) {
    if (!confirm('Вы уверены, что хотите разархивировать это событие?')) {
        return;
    }
    
    fetch(`/admin/events/${eventId}/unarchive`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => {
        if (response.ok) {
            location.reload();
        } else {
            alert('Ошибка при разархивировании события');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Ошибка при разархивировании события');
    });
}
</script>
@endpush
