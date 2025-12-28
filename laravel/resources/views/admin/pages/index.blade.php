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
                <label class="block text-sm font-medium text-gray-300 mb-2">Содержимое (HTML)</label>
                <textarea name="page_content" rows="10" class="w-full px-4 py-2 bg-[#121212] border border-gray-700 rounded-lg text-white" required></textarea>
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
    document.getElementById('page-modal').classList.remove('hidden');
}

function openEditModal(pageId) {
    // TODO: Загрузить данные страницы и заполнить форму
    const form = document.getElementById('page-form');
    form.action = '{{ route("admin.pages.update", ":id") }}'.replace(':id', pageId);
    form.querySelector('#form-method').innerHTML = '<input type="hidden" name="_method" value="PUT">';
    document.getElementById('page-modal').classList.remove('hidden');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.add('hidden');
}
</script>
@endpush


