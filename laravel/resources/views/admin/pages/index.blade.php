@extends('layouts.app')

@section('title', 'Страницы сайта - Hunter-Photo.Ru')
@section('page-title', 'Управление страницами')

@section('content')
    <div class="mb-6">
        <x-button onclick="openCreateModal()">
            <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
            </svg>
            Создать страницу
        </x-button>
    </div>

    @if($pages->count() > 0)
        <x-table :headers="['Название', 'URL', 'Действия']">
            @foreach($pages as $page)
                <tr class="hover:bg-[#1e1e1e] transition-colors">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm font-medium text-white">{{ $page->page_title }}</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">{{ $page->page_url }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                        <div class="flex space-x-2">
                            <x-button onclick="openEditModal('{{ $page->id }}')" size="sm" variant="outline">
                                Редактировать
                            </x-button>
                            <x-button href="{{ route('admin.pages.destroy', $page->id) }}" size="sm" variant="danger" onclick="return confirm('Удалить страницу?')">
                                Удалить
                            </x-button>
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-table>

        <div class="mt-6">
            {{ $pages->links() }}
        </div>
    @else
        <x-empty-state 
            title="Нет страниц" 
            description="Создайте первую страницу"
        >
            <x-slot:action>
                <x-button onclick="openCreateModal()">Создать страницу</x-button>
            </x-slot:action>
        </x-empty-state>
    @endif

    <!-- Модальное окно создания/редактирования -->
    <x-modal id="page-modal" title="Страница" size="xl">
        <form id="page-form" method="POST">
            @csrf
            <div id="form-method"></div>
            
            <x-input label="Название страницы" name="page_title" required />
            <x-input label="URL" name="page_url" placeholder="/about" required />
            <x-textarea label="Meta Description" name="page_meta_descr" rows="2" />
            <x-textarea label="Meta Keywords" name="page_meta_key" rows="2" />
            <div class="mb-4">
                <div class="flex items-center justify-between mb-2">
                    <label class="block text-sm font-medium text-gray-300">Содержимое (HTML)</label>
                    <button type="button" onclick="toggleHelp()" class="text-sm text-[#a78bfa] hover:text-[#8b6cf7]">
                        <svg class="w-5 h-5 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        Помощь
                    </button>
                </div>
                
                <!-- Панель инструментов для вставки HTML -->
                <div id="html-toolbar" class="mb-2 p-3 bg-[#1e1e1e] rounded-lg border border-gray-700">
                    <div class="flex flex-wrap gap-2">
                        <button type="button" data-open="<h1>" data-close="</h1>" class="html-insert-btn px-3 py-1.5 bg-gray-700 hover:bg-gray-600 text-white text-sm rounded transition-colors" title="Заголовок 1">
                            H1
                        </button>
                        <button type="button" data-open="<h2>" data-close="</h2>" class="html-insert-btn px-3 py-1.5 bg-gray-700 hover:bg-gray-600 text-white text-sm rounded transition-colors" title="Заголовок 2">
                            H2
                        </button>
                        <button type="button" data-open="<h3>" data-close="</h3>" class="html-insert-btn px-3 py-1.5 bg-gray-700 hover:bg-gray-600 text-white text-sm rounded transition-colors" title="Заголовок 3">
                            H3
                        </button>
                        <button type="button" data-open="<p>" data-close="</p>" class="html-insert-btn px-3 py-1.5 bg-gray-700 hover:bg-gray-600 text-white text-sm rounded transition-colors" title="Параграф">
                            P
                        </button>
                        <button type="button" data-open="<strong>" data-close="</strong>" class="html-insert-btn px-3 py-1.5 bg-gray-700 hover:bg-gray-600 text-white text-sm rounded transition-colors font-bold" title="Жирный текст">
                            B
                        </button>
                        <button type="button" data-open="<em>" data-close="</em>" class="html-insert-btn px-3 py-1.5 bg-gray-700 hover:bg-gray-600 text-white text-sm rounded transition-colors italic" title="Курсив">
                            I
                        </button>
                        <button type="button" data-open="<ul>&#10;<li>" data-close="</li>&#10;</ul>" class="html-insert-btn px-3 py-1.5 bg-gray-700 hover:bg-gray-600 text-white text-sm rounded transition-colors" title="Маркированный список">
                            • Список
                        </button>
                        <button type="button" data-open="<ol>&#10;<li>" data-close="</li>&#10;</ol>" class="html-insert-btn px-3 py-1.5 bg-gray-700 hover:bg-gray-600 text-white text-sm rounded transition-colors" title="Нумерованный список">
                            1. Список
                        </button>
                        <button type="button" data-open='<a href="">' data-close="</a>" class="html-insert-btn px-3 py-1.5 bg-gray-700 hover:bg-gray-600 text-white text-sm rounded transition-colors" title="Ссылка">
                            🔗 Ссылка
                        </button>
                        <button type="button" data-open="<br>" data-close="" class="html-insert-btn px-3 py-1.5 bg-gray-700 hover:bg-gray-600 text-white text-sm rounded transition-colors" title="Перенос строки">
                            ↵
                        </button>
                        <button type="button" data-open='<div class="mb-4">' data-close="</div>" class="html-insert-btn px-3 py-1.5 bg-gray-700 hover:bg-gray-600 text-white text-sm rounded transition-colors" title="Блок">
                            📦 Блок
                        </button>
                    </div>
                </div>
                
                <textarea id="page-content-textarea" name="page_content" rows="12" class="w-full px-4 py-2 bg-[#121212] border border-gray-700 rounded-lg text-white font-mono text-sm" required placeholder="Введите HTML содержимое или используйте кнопки выше для вставки тегов"></textarea>
                
                <!-- Панель помощи -->
                <div id="help-panel" class="hidden mt-4 p-4 bg-[#1e1e1e] rounded-lg border border-gray-700">
                    <h4 class="text-white font-semibold mb-3">Помощь по HTML</h4>
                    <div class="space-y-4 text-sm">
                        <div>
                            <p class="text-gray-300 mb-2"><strong class="text-white">Основные теги:</strong></p>
                            <div class="bg-[#121212] p-3 rounded font-mono text-xs text-gray-400 space-y-1">
                                <div>&lt;h1&gt;Заголовок&lt;/h1&gt; - Заголовок первого уровня</div>
                                <div>&lt;h2&gt;Подзаголовок&lt;/h2&gt; - Заголовок второго уровня</div>
                                <div>&lt;p&gt;Текст параграфа&lt;/p&gt; - Параграф текста</div>
                                <div>&lt;strong&gt;Жирный текст&lt;/strong&gt; - Жирное начертание</div>
                                <div>&lt;em&gt;Курсив&lt;/em&gt; - Курсивное начертание</div>
                                <div>&lt;br&gt; - Перенос строки</div>
                            </div>
                        </div>
                        
                        <div>
                            <p class="text-gray-300 mb-2"><strong class="text-white">Списки:</strong></p>
                            <div class="bg-[#121212] p-3 rounded font-mono text-xs text-gray-400 space-y-1">
                                <div>&lt;ul&gt;</div>
                                <div class="pl-4">&lt;li&gt;Элемент 1&lt;/li&gt;</div>
                                <div class="pl-4">&lt;li&gt;Элемент 2&lt;/li&gt;</div>
                                <div>&lt;/ul&gt;</div>
                            </div>
                        </div>
                        
                        <div>
                            <p class="text-gray-300 mb-2"><strong class="text-white">Ссылки:</strong></p>
                            <div class="bg-[#121212] p-3 rounded font-mono text-xs text-gray-400">
                                &lt;a href="https://example.com"&gt;Текст ссылки&lt;/a&gt;
                            </div>
                        </div>
                        
                        <div>
                            <p class="text-gray-300 mb-2"><strong class="text-white">Пример готового блока:</strong></p>
                            <div class="bg-[#121212] p-3 rounded font-mono text-xs text-gray-400 space-y-1">
                                <div>&lt;h1&gt;Заголовок страницы&lt;/h1&gt;</div>
                                <div>&lt;p&gt;Это первый параграф текста.&lt;/p&gt;</div>
                                <div>&lt;p&gt;Это второй параграф с &lt;strong&gt;жирным текстом&lt;/strong&gt;.&lt;/p&gt;</div>
                                <div>&lt;ul&gt;</div>
                                <div class="pl-4">&lt;li&gt;Пункт списка 1&lt;/li&gt;</div>
                                <div class="pl-4">&lt;li&gt;Пункт списка 2&lt;/li&gt;</div>
                                <div>&lt;/ul&gt;</div>
                            </div>
                        </div>
                        
                        <div class="pt-2 border-t border-gray-700">
                            <p class="text-gray-400 text-xs">
                                💡 <strong class="text-white">Совет:</strong> Используйте кнопки выше для быстрой вставки тегов. Выделите текст в поле ввода и нажмите нужную кнопку, чтобы обернуть его в тег.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="flex space-x-3">
                <x-button type="submit">Сохранить</x-button>
                <x-button variant="outline" type="button" onclick="closeModal('page-modal')">Отмена</x-button>
            </div>
        </form>
    </x-modal>
@endsection

@push('scripts')
<script>
function openCreateModal() {
    const form = document.getElementById('page-form');
    form.action = '{{ route("admin.pages.store") }}';
    form.querySelector('#form-method').innerHTML = '';
    form.reset();
    document.getElementById('help-panel').classList.add('hidden');
    document.getElementById('page-modal').classList.remove('hidden');
}

function openEditModal(pageId) {
    // Загрузить данные страницы через AJAX
    fetch(`/admin/pages/${pageId}/edit-data`)
        .then(response => response.json())
        .then(data => {
            const form = document.getElementById('page-form');
            form.action = '{{ route("admin.pages.update", ":id") }}'.replace(':id', pageId);
            form.querySelector('#form-method').innerHTML = '<input type="hidden" name="_method" value="PUT">';
            
            form.querySelector('[name="page_title"]').value = data.page_title || '';
            form.querySelector('[name="page_url"]').value = data.page_url || '';
            form.querySelector('[name="page_meta_descr"]').value = data.page_meta_descr || '';
            form.querySelector('[name="page_meta_key"]').value = data.page_meta_key || '';
            document.getElementById('page-content-textarea').value = data.page_content || '';
            
            document.getElementById('help-panel').classList.add('hidden');
            document.getElementById('page-modal').classList.remove('hidden');
        })
        .catch(error => {
            console.error('Error loading page data:', error);
            alert('Ошибка загрузки данных страницы');
        });
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.add('hidden');
}

function toggleHelp() {
    const panel = document.getElementById('help-panel');
    panel.classList.toggle('hidden');
}

// Инициализация обработчиков для кнопок вставки HTML
document.addEventListener('DOMContentLoaded', function() {
    const buttons = document.querySelectorAll('.html-insert-btn');
    buttons.forEach(button => {
        button.addEventListener('click', function() {
            const openTag = this.getAttribute('data-open') || '';
            const closeTag = this.getAttribute('data-close') || '';
            insertHTML(openTag, closeTag);
        });
    });
});

function insertHTML(openTag, closeTag) {
    const textarea = document.getElementById('page-content-textarea');
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const selectedText = textarea.value.substring(start, end);
    const textBefore = textarea.value.substring(0, start);
    const textAfter = textarea.value.substring(end);
    
    // Декодируем HTML entities (&#10; -> \n)
    const decodedOpenTag = openTag.replace(/&#10;/g, '\n');
    const decodedCloseTag = closeTag.replace(/&#10;/g, '\n');
    
    let newText;
    if (selectedText) {
        // Если есть выделенный текст, оборачиваем его в теги
        newText = textBefore + decodedOpenTag + selectedText + decodedCloseTag + textAfter;
        textarea.value = newText;
        // Устанавливаем курсор после закрывающего тега
        textarea.setSelectionRange(start + decodedOpenTag.length + selectedText.length + decodedCloseTag.length, start + decodedOpenTag.length + selectedText.length + decodedCloseTag.length);
    } else {
        // Если нет выделенного текста, вставляем теги с плейсхолдером
        const placeholder = decodedCloseTag ? 'текст' : '';
        newText = textBefore + decodedOpenTag + placeholder + decodedCloseTag + textAfter;
        textarea.value = newText;
        // Устанавливаем курсор внутри тегов
        if (decodedCloseTag) {
            const cursorPos = start + decodedOpenTag.length;
            textarea.setSelectionRange(cursorPos, cursorPos + placeholder.length);
        } else {
            textarea.setSelectionRange(start + decodedOpenTag.length, start + decodedOpenTag.length);
        }
    }
    
    textarea.focus();
}
</script>
@endpush


