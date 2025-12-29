@extends('layouts.app')

@section('title', 'Все фотографии - Hunter-Photo.Ru')
@section('page-title', 'Все фотографии')

@section('content')
    <div class="mb-6 space-y-4">
        <!-- Статистика -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="bg-[#1e1e1e] rounded-lg p-4">
                <div class="text-sm text-gray-400">Всего фотографий</div>
                <div class="text-2xl font-bold text-white mt-1">{{ number_format($stats['total']) }}</div>
            </div>
            <div class="bg-[#1e1e1e] rounded-lg p-4">
                <div class="text-sm text-gray-400">С лицами</div>
                <div class="text-2xl font-bold text-green-400 mt-1">{{ number_format($stats['with_faces']) }}</div>
            </div>
            <div class="bg-[#1e1e1e] rounded-lg p-4">
                <div class="text-sm text-gray-400">С номерами</div>
                <div class="text-2xl font-bold text-blue-400 mt-1">{{ number_format($stats['with_numbers']) }}</div>
            </div>
            <div class="bg-[#1e1e1e] rounded-lg p-4">
                <div class="text-sm text-gray-400">На S3</div>
                <div class="text-2xl font-bold text-purple-400 mt-1">{{ number_format($stats['with_s3']) }}</div>
            </div>
        </div>

        <!-- Фильтры -->
        <div class="bg-[#1e1e1e] rounded-lg p-4">
            <form method="GET" action="{{ route('admin.photos.list') }}" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <!-- Поиск -->
                    <div>
                        <label class="block text-sm text-gray-400 mb-1">Поиск</label>
                        <input 
                            type="text" 
                            name="search" 
                            value="{{ request('search') }}"
                            placeholder="ID, имя файла..."
                            class="w-full px-3 py-2 bg-[#121212] border border-gray-700 rounded text-white text-sm focus:outline-none focus:border-[#a78bfa]"
                        >
                    </div>

                    <!-- Событие -->
                    <div>
                        <label class="block text-sm text-gray-400 mb-1">Событие</label>
                        <select 
                            name="event_id" 
                            class="w-full px-3 py-2 bg-[#121212] border border-gray-700 rounded text-white text-sm focus:outline-none focus:border-[#a78bfa]"
                        >
                            <option value="">Все события</option>
                            @foreach($events as $event)
                                <option value="{{ $event->id }}" {{ request('event_id') == $event->id ? 'selected' : '' }}>
                                    {{ $event->title }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Наличие лиц -->
                    <div>
                        <label class="block text-sm text-gray-400 mb-1">Лица</label>
                        <select 
                            name="has_faces" 
                            class="w-full px-3 py-2 bg-[#121212] border border-gray-700 rounded text-white text-sm focus:outline-none focus:border-[#a78bfa]"
                        >
                            <option value="">Все</option>
                            <option value="1" {{ request('has_faces') === '1' ? 'selected' : '' }}>Есть лица</option>
                            <option value="0" {{ request('has_faces') === '0' ? 'selected' : '' }}>Нет лиц</option>
                        </select>
                    </div>

                    <!-- Наличие номеров -->
                    <div>
                        <label class="block text-sm text-gray-400 mb-1">Номера</label>
                        <select 
                            name="has_numbers" 
                            class="w-full px-3 py-2 bg-[#121212] border border-gray-700 rounded text-white text-sm focus:outline-none focus:border-[#a78bfa]"
                        >
                            <option value="">Все</option>
                            <option value="1" {{ request('has_numbers') === '1' ? 'selected' : '' }}>Есть номера</option>
                            <option value="0" {{ request('has_numbers') === '0' ? 'selected' : '' }}>Нет номеров</option>
                        </select>
                    </div>
                </div>

                <div class="flex justify-between items-center">
                    <div class="flex space-x-2">
                        <button 
                            type="submit" 
                            class="px-4 py-2 bg-[#a78bfa] hover:bg-[#8b5cf6] text-white rounded transition-colors"
                        >
                            Применить фильтры
                        </button>
                        <a 
                            href="{{ route('admin.photos.list') }}" 
                            class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded transition-colors"
                        >
                            Сбросить
                        </a>
                    </div>

                    <!-- Сортировка -->
                    <div class="flex items-center space-x-2">
                        <label class="text-sm text-gray-400">Сортировка:</label>
                        <select 
                            name="sort_by" 
                            onchange="this.form.submit()"
                            class="px-3 py-2 bg-[#121212] border border-gray-700 rounded text-white text-sm focus:outline-none focus:border-[#a78bfa]"
                        >
                            <option value="created_at" {{ request('sort_by') === 'created_at' ? 'selected' : '' }}>Дата создания</option>
                            <option value="price" {{ request('sort_by') === 'price' ? 'selected' : '' }}>Цена</option>
                            <option value="id" {{ request('sort_by') === 'id' ? 'selected' : '' }}>ID</option>
                        </select>
                        <select 
                            name="sort_order" 
                            onchange="this.form.submit()"
                            class="px-3 py-2 bg-[#121212] border border-gray-700 rounded text-white text-sm focus:outline-none focus:border-[#a78bfa]"
                        >
                            <option value="desc" {{ request('sort_order') === 'desc' ? 'selected' : '' }}>По убыванию</option>
                            <option value="asc" {{ request('sort_order') === 'asc' ? 'selected' : '' }}>По возрастанию</option>
                        </select>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Таблица фотографий -->
    @if($photos->count() > 0)
        <div class="bg-[#1e1e1e] rounded-lg overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-800">
                    <thead class="bg-[#121212]">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">ID</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Превью</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Событие</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Имя файла</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Цена</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Лица</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Номера</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">S3</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Дата</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Действия</th>
                        </tr>
                    </thead>
                    <tbody class="bg-[#1e1e1e] divide-y divide-gray-800">
                        @foreach($photos as $photo)
                            <tr class="hover:bg-[#252525] transition-colors">
                                <td class="px-4 py-3 text-sm text-gray-300 font-mono">
                                    <span class="text-xs">{{ substr($photo->id, 0, 8) }}...</span>
                                </td>
                                <td class="px-4 py-3">
                                    @if($photo->getDisplayUrl())
                                        <img 
                                            src="{{ $photo->getDisplayUrl() }}" 
                                            alt="Preview" 
                                            class="w-16 h-16 object-cover rounded"
                                            onerror="this.src='data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'64\' height=\'64\'%3E%3Crect fill=\'%23333\' width=\'64\' height=\'64\'/%3E%3Ctext x=\'50%25\' y=\'50%25\' text-anchor=\'middle\' dy=\'.3em\' fill=\'%23999\' font-size=\'10\'%3ENo image%3C/text%3E%3C/svg%3E'"
                                        >
                                    @else
                                        <div class="w-16 h-16 bg-gray-800 rounded flex items-center justify-center text-xs text-gray-500">
                                            Нет фото
                                        </div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-300">
                                    @if($photo->event)
                                        <a href="{{ route('admin.events.edit', $photo->event->id) }}" class="text-[#a78bfa] hover:text-[#8b5cf6]">
                                            {{ Str::limit($photo->event->title, 30) }}
                                        </a>
                                    @else
                                        <span class="text-gray-500">-</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-300">
                                    <div class="max-w-xs truncate" title="{{ $photo->original_name ?? $photo->custom_name ?? '-' }}">
                                        {{ $photo->original_name ?? $photo->custom_name ?? '-' }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-300">
                                    {{ $photo->price ? number_format($photo->price, 2) . ' ₽' : '-' }}
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    @if($photo->has_faces)
                                        <span class="px-2 py-1 bg-green-500/20 text-green-400 rounded text-xs">Да</span>
                                        @if($photo->face_encodings && count($photo->face_encodings) > 0)
                                            <span class="text-xs text-gray-500">({{ count($photo->face_encodings) }})</span>
                                        @endif
                                    @else
                                        <span class="px-2 py-1 bg-gray-700 text-gray-400 rounded text-xs">Нет</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    @if($photo->numbers && count($photo->numbers) > 0)
                                        <span class="px-2 py-1 bg-blue-500/20 text-blue-400 rounded text-xs">
                                            Да ({{ count($photo->numbers) }})
                                        </span>
                                    @else
                                        <span class="px-2 py-1 bg-gray-700 text-gray-400 rounded text-xs">Нет</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    @if($photo->s3_custom_url || $photo->s3_original_url)
                                        <span class="px-2 py-1 bg-purple-500/20 text-purple-400 rounded text-xs">Да</span>
                                    @else
                                        <span class="px-2 py-1 bg-gray-700 text-gray-400 rounded text-xs">Нет</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-400">
                                    {{ $photo->created_at ? $photo->created_at->format('d.m.Y H:i') : '-' }}
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <a 
                                        href="{{ route('admin.photos.show', $photo->id) }}" 
                                        class="px-3 py-1 bg-[#a78bfa] hover:bg-[#8b5cf6] text-white rounded text-xs transition-colors"
                                    >
                                        Детали
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Пагинация -->
            <div class="px-4 py-3 bg-[#121212] border-t border-gray-800">
                {{ $photos->links('vendor.pagination.default') }}
            </div>
        </div>
    @else
        <div class="bg-[#1e1e1e] rounded-lg p-8 text-center">
            <p class="text-gray-400">Фотографии не найдены</p>
        </div>
    @endif
@endsection

