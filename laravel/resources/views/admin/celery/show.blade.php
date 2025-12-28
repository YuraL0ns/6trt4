@extends('layouts.app')

@section('title', 'Задачи события - Hunter-Photo.Ru')
@section('page-title', 'Задачи события: ' . $event->title)

@section('content')
    <div class="mb-4 flex items-center justify-between">
        <x-button href="{{ route('admin.celery.index') }}" variant="outline">
            <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
            </svg>
            Назад к списку
        </x-button>
        
        <div class="flex space-x-3">
            <x-button onclick="loadLogs()" variant="outline">
                <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                Просмотр логов
            </x-button>
            <form action="{{ route('admin.celery.restart', $event->id) }}" method="POST" onsubmit="return confirm('Вы уверены, что хотите перезапустить задачи для этого события?');">
                @csrf
                <x-button type="submit" variant="warning">
                    <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                    </svg>
                    Перезапустить задачи
                </x-button>
            </form>
        </div>
    </div>

    <!-- Основная информация о событии -->
    <x-card title="Информация о событии" class="mb-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <p class="text-sm text-gray-400 mb-1">Название</p>
                <p class="text-white font-medium">{{ $event->title }}</p>
            </div>
            <div>
                <p class="text-sm text-gray-400 mb-1">Город</p>
                <p class="text-white">{{ $event->city }}</p>
            </div>
            <div>
                <p class="text-sm text-gray-400 mb-1">Дата проведения</p>
                <p class="text-white">{{ $event->date->format('d.m.Y') }}</p>
            </div>
            <div>
                <p class="text-sm text-gray-400 mb-1">Статус события</p>
                <x-badge variant="{{ $event->status === 'published' ? 'success' : ($event->status === 'processing' ? 'warning' : 'default') }}">
                    @if($event->status === 'draft') Черновик
                    @elseif($event->status === 'processing') В обработке
                    @elseif($event->status === 'published') Опубликовано
                    @elseif($event->status === 'completed') Завершено
                    @elseif($event->status === 'archived') Архивировано
                    @endif
                </x-badge>
            </div>
            <div>
                <p class="text-sm text-gray-400 mb-1">Фотограф</p>
                <p class="text-white">{{ $event->author->full_name ?? '-' }}</p>
            </div>
            <div>
                <p class="text-sm text-gray-400 mb-1">Количество фотографий</p>
                <p class="text-white">{{ $event->photos->count() }}</p>
            </div>
        </div>
    </x-card>

    <!-- Типы анализов и их статусы -->
    <x-card title="Типы анализов">
        <div class="space-y-4">
            @foreach($analysisTypes as $typeKey => $typeInfo)
                @php
                    $task = $event->celeryTasks->where('task_type', $typeKey)->first();
                    $status = $task ? $task->status : 'pending';
                    $progress = $task ? $task->progress : 0;
                    
                    // Получаем детальную информацию из event_info если доступна
                    $detailInfo = null;
                    if ($eventInfo && isset($eventInfo[$typeInfo['key']])) {
                        $detailInfo = $eventInfo[$typeInfo['key']];
                    }
                @endphp
                <div class="border border-gray-800 rounded-lg p-4">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center space-x-3">
                            <h3 class="text-lg font-semibold text-white">{{ $typeInfo['name'] }}</h3>
                            <x-badge variant="{{ $status === 'completed' ? 'success' : ($status === 'processing' ? 'warning' : ($status === 'failed' ? 'error' : 'default')) }}">
                                @if($status === 'pending') Ожидание
                                @elseif($status === 'processing') В процессе
                                @elseif($status === 'completed') Завершено
                                @elseif($status === 'failed') Ошибка
                                @else Не запущено
                                @endif
                            </x-badge>
                        </div>
                        @if($task)
                            <div class="flex items-center space-x-3">
                                <div class="text-sm text-gray-400">
                                    ID задачи: <span class="font-mono">{{ substr($task->task_id, 0, 20) }}...</span>
                                </div>
                                <div class="flex space-x-2">
                                    <button onclick="loadTaskLog('{{ $task->id }}')" class="px-3 py-1 text-xs bg-gray-700 hover:bg-gray-600 text-white rounded transition-colors">
                                        <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                        </svg>
                                        Логи
                                    </button>
                                    <form action="{{ route('admin.celery.tasks.restart', $task->id) }}" method="POST" onsubmit="return confirm('Вы уверены, что хотите перезапустить задачу \"{{ $typeInfo['name'] }}\"?');" class="inline" id="restart-form-{{ $task->id }}">
                                        @csrf
                                        <button type="submit" class="px-3 py-1 text-xs bg-yellow-600 hover:bg-yellow-700 text-white rounded transition-colors" id="restart-btn-{{ $task->id }}">
                                            <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                            </svg>
                                            Перезапустить
                                        </button>
                                    </form>
                                    <script>
                                        document.getElementById('restart-form-{{ $task->id }}').addEventListener('submit', function(e) {
                                            const btn = document.getElementById('restart-btn-{{ $task->id }}');
                                            btn.disabled = true;
                                            btn.innerHTML = '<svg class="w-4 h-4 inline mr-1 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>Перезапуск...';
                                            
                                            // После успешной отправки формы обновляем страницу через 2 секунды
                                            setTimeout(function() {
                                                window.location.reload();
                                            }, 2000);
                                        });
                                    </script>
                                    <form action="{{ route('admin.celery.tasks.delete', $task->id) }}" method="POST" onsubmit="return confirm('Вы уверены, что хотите удалить задачу \"{{ $typeInfo['name'] }}\"? Это действие нельзя отменить.');" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="px-3 py-1 text-xs bg-red-600 hover:bg-red-700 text-white rounded transition-colors">
                                            <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                            </svg>
                                            Очистить
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @endif
                    </div>
                    
                    @if($task)
                        <div class="mb-3">
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-sm text-gray-400">Прогресс</span>
                                <span class="text-sm text-gray-300">{{ $progress }}%</span>
                            </div>
                            <div class="w-full bg-gray-700 rounded-full h-2.5">
                                <div class="bg-[#a78bfa] h-2.5 rounded-full transition-all" style="width: {{ $progress }}%"></div>
                            </div>
                        </div>
                        
                        @if($task->error_message)
                            <div class="mt-3 p-3 bg-red-900/20 border border-red-800 rounded-lg">
                                <p class="text-sm text-red-400">Ошибка: {{ $task->error_message }}</p>
                            </div>
                        @endif
                        
                        @if($detailInfo && is_array($detailInfo))
                            <div class="mt-3">
                                <p class="text-sm text-gray-400 mb-2">Детальная информация:</p>
                                <div class="bg-[#121212] rounded-lg p-3">
                                    <p class="text-xs text-gray-500 font-mono">
                                        Обработано: {{ count(array_filter($detailInfo, fn($item) => isset($item['status']) && strtolower(trim($item['status'])) === 'ready')) }} / {{ count($detailInfo) }}
                                    </p>
                                    @if($task->created_at)
                                        <p class="text-xs text-gray-500 mt-1">Создано: {{ $task->created_at->format('d.m.Y H:i:s') }}</p>
                                    @endif
                                    @if($task->updated_at)
                                        <p class="text-xs text-gray-500 mt-1">Обновлено: {{ $task->updated_at->format('d.m.Y H:i:s') }}</p>
                                    @endif
                                </div>
                            </div>
                        @endif
                    @else
                        <p class="text-sm text-gray-500">Задача не запущена</p>
                    @endif
                </div>
            @endforeach
        </div>
    </x-card>
@endsection

@push('scripts')
<script>
// Автообновление каждые 5 секунд
setInterval(function() {
    window.location.reload();
}, 5000);

// Функция загрузки логов события
function loadLogs() {
    fetch('{{ route("admin.celery.logs", $event->id) }}', {
        method: 'GET',
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Создаем модальное окно с логами
            const logsHtml = `
                <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" onclick="closeLogModal()">
                    <div class="bg-[#1a1a1a] rounded-lg p-6 max-w-4xl w-full mx-4 max-h-[90vh] overflow-y-auto" onclick="event.stopPropagation()">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold text-white">Логи для события: ${data.event_title}</h3>
                            <button onclick="closeLogModal()" class="text-gray-400 hover:text-white">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>
                        <div class="space-y-3">
                            ${data.logs.map(log => `
                                <div class="p-4 bg-[#121212] rounded-lg border border-gray-800">
                                    <div class="flex items-center justify-between mb-2">
                                        <span class="text-sm font-semibold text-white">${log.task_type_name || log.task_type || log.source || 'Лог'}</span>
                                        <span class="text-xs text-gray-400">${log.created_at || log.last_updated || ''}</span>
                                    </div>
                                    ${log.status ? `<p class="text-xs text-gray-400 mb-1">Статус: <span class="text-white">${log.status}</span></p>` : ''}
                                    ${log.progress !== undefined ? `<p class="text-xs text-gray-400 mb-1">Прогресс: <span class="text-white">${log.progress}%</span></p>` : ''}
                                    ${log.celery_state ? `<p class="text-xs text-gray-400 mb-1">Состояние Celery: <span class="text-white">${log.celery_state}</span></p>` : ''}
                                    ${log.celery_status ? `<p class="text-xs text-gray-400 mb-1">Статус Celery: <span class="text-white">${log.celery_status}</span></p>` : ''}
                                    ${log.event_info ? `<p class="text-xs text-gray-400 mb-1">Обработано: <span class="text-white">${log.event_info.completed}/${log.event_info.total}</span></p>` : ''}
                                    ${log.photo_count !== undefined ? `<p class="text-xs text-gray-400 mb-1">Фотографий: <span class="text-white">${log.photo_count}</span></p>` : ''}
                                    ${log.error_message ? `<p class="text-xs text-red-400 mt-2">Ошибка: ${log.error_message}</p>` : ''}
                                    ${log.celery_error ? `<p class="text-xs text-red-400 mt-2">Ошибка Celery: ${log.celery_error}</p>` : ''}
                                    ${log.celery_task_id ? `<p class="text-xs text-gray-500 mt-2 font-mono">Task ID: ${log.celery_task_id}</p>` : ''}
                                </div>
                            `).join('')}
                        </div>
                    </div>
                </div>
            `;
            
            // Добавляем модальное окно в DOM
            const modal = document.createElement('div');
            modal.id = 'log-modal';
            modal.innerHTML = logsHtml;
            document.body.appendChild(modal);
        } else {
            alert('Ошибка при загрузке логов: ' + (data.error || 'Неизвестная ошибка'));
        }
    })
    .catch(error => {
        console.error('Error loading logs:', error);
        alert('Ошибка при загрузке логов: ' + error.message);
    });
}

// Функция закрытия модального окна логов
function closeLogModal() {
    const modal = document.getElementById('log-modal');
    if (modal) {
        modal.remove();
    }
}

// Функция загрузки логов отдельной задачи
function loadTaskLog(taskId) {
    fetch(`{{ route('admin.celery.tasks.log', '') }}/${taskId}`, {
        method: 'GET',
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const log = data.log;
            const logHtml = `
                <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" onclick="closeTaskLogModal()">
                    <div class="bg-[#1a1a1a] rounded-lg p-6 max-w-3xl w-full mx-4 max-h-[90vh] overflow-y-auto" onclick="event.stopPropagation()">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold text-white">Логи задачи: ${log.task_type_name || log.task_type}</h3>
                            <button onclick="closeTaskLogModal()" class="text-gray-400 hover:text-white">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>
                        <div class="space-y-3">
                            <div class="p-4 bg-[#121212] rounded-lg border border-gray-800">
                                <p class="text-sm text-gray-400 mb-1">Статус: <span class="text-white">${log.status}</span></p>
                                <p class="text-sm text-gray-400 mb-1">Прогресс: <span class="text-white">${log.progress}%</span></p>
                                ${log.celery_state ? `<p class="text-sm text-gray-400 mb-1">Состояние Celery: <span class="text-white">${log.celery_state}</span></p>` : ''}
                                ${log.celery_status ? `<p class="text-sm text-gray-400 mb-1">Статус Celery: <span class="text-white">${log.celery_status}</span></p>` : ''}
                                <p class="text-sm text-gray-400 mb-1">Создано: <span class="text-white">${log.created_at}</span></p>
                                <p class="text-sm text-gray-400 mb-1">Обновлено: <span class="text-white">${log.updated_at}</span></p>
                                ${log.celery_task_id ? `<p class="text-sm text-gray-400 mb-1">Task ID: <span class="text-white font-mono text-xs">${log.celery_task_id}</span></p>` : ''}
                                ${log.error_message ? `<p class="text-sm text-red-400 mt-2">Ошибка: ${log.error_message}</p>` : ''}
                                ${log.celery_error ? `<p class="text-sm text-red-400 mt-2">Ошибка Celery: ${log.celery_error}</p>` : ''}
                                ${log.event_info ? `
                                    <div class="mt-3 pt-3 border-t border-gray-700">
                                        <p class="text-sm text-gray-400 mb-2">Информация из event_info.json:</p>
                                        <p class="text-xs text-gray-500">Обработано: <span class="text-white">${log.event_info.completed}/${log.event_info.total}</span></p>
                                        ${log.event_info.last_updated ? `<p class="text-xs text-gray-500 mt-1">Последнее обновление: <span class="text-white">${log.event_info.last_updated}</span></p>` : ''}
                                    </div>
                                ` : ''}
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            const modal = document.createElement('div');
            modal.id = 'task-log-modal';
            modal.innerHTML = logHtml;
            document.body.appendChild(modal);
        } else {
            alert('Ошибка при загрузке логов: ' + (data.error || 'Неизвестная ошибка'));
        }
    })
    .catch(error => {
        console.error('Error loading task log:', error);
        alert('Ошибка при загрузке логов: ' + error.message);
    });
}

// Функция закрытия модального окна логов задачи
function closeTaskLogModal() {
    const modal = document.getElementById('task-log-modal');
    if (modal) {
        modal.remove();
    }
}
</script>
@endpush



