@extends('layouts.app')

@section('title', 'Celery задачи - Hunter-Photo.Ru')
@section('page-title', 'Консоль Celery')

@section('content')
    <div class="mb-4">
        <x-button onclick="refreshEvents()" variant="outline">
            <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
            </svg>
            Обновить
        </x-button>
    </div>

    <x-card>
        <div id="events-container">
            @if($events->count() > 0)
                <x-table :headers="['Событие', 'Статус события', 'Кол-во задач', 'Общий прогресс', 'Создано', 'Действия']">
                    @foreach($events as $event)
                        @php
                            // Используем метод связи напрямую для максимальной надежности
                            $tasks = $event->celeryTasks()->get();
                            
                            // Вычисляем статистику
                            $tasksCount = $tasks->count();
                            $totalProgress = $tasksCount > 0 ? intval($tasks->avg('progress')) : 0;
                            $hasCompleted = $tasks->where('status', 'completed')->count();
                            $hasProcessing = $tasks->where('status', 'processing')->count();
                            $hasFailed = $tasks->where('status', 'failed')->count();
                            $eventStatus = $hasFailed > 0 ? 'error' : ($hasProcessing > 0 ? 'processing' : ($hasCompleted === $tasksCount && $tasksCount > 0 ? 'completed' : 'pending'));
                        @endphp
                        <tr class="hover:bg-[#1e1e1e] transition-colors">
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-white">{{ $event->title }}</div>
                                <div class="text-xs text-gray-400">{{ $event->city }}, {{ $event->date->format('d.m.Y') }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <x-badge variant="{{ $eventStatus === 'completed' ? 'success' : ($eventStatus === 'processing' ? 'warning' : ($eventStatus === 'failed' ? 'error' : 'default')) }}">
                                    @if($eventStatus === 'pending') Ожидание
                                    @elseif($eventStatus === 'processing') В процессе
                                    @elseif($eventStatus === 'completed') Завершено
                                    @else Ошибка
                                    @endif
                                </x-badge>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">
                                {{ $tasksCount }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="w-32 bg-gray-700 rounded-full h-2">
                                    <div class="bg-[#a78bfa] h-2 rounded-full transition-all" style="width: {{ $totalProgress }}%"></div>
                                </div>
                                <span class="text-xs text-gray-400">{{ $totalProgress }}%</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-400">{{ $event->created_at->format('d.m.Y H:i') }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <a href="{{ route('admin.celery.show', $event->id) }}" class="text-[#a78bfa] hover:text-[#8b5cf6] transition-colors">
                                    Просмотр задач
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </x-table>
                
                <div class="mt-4">
                    {{ $events->links() }}
                </div>
            @else
                <x-empty-state 
                    title="Нет событий с задачами" 
                    description="В системе пока нет событий с активными задачами Celery"
                />
            @endif
        </div>
    </x-card>
@endsection

@push('scripts')
<script>
function refreshEvents() {
    fetch('{{ route("admin.celery.index") }}?ajax=1')
        .then(response => response.json())
        .then(data => {
            // Перезагружаем страницу для обновления данных
            window.location.reload();
        });
}

// Автообновление каждые 10 секунд
setInterval(refreshEvents, 10000);
</script>
@endpush


