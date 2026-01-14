@extends('layouts.app')

@section('title', 'Создать событие - Hunter-Photo.Ru')
@section('page-title', 'Создать событие')

@section('content')
    <div class="bg-[#1e1e1e] border border-gray-800 rounded-lg p-6">
        <form action="{{ route('photo.events.store') }}" method="POST" enctype="multipart/form-data" id="event-create-form">
            @csrf
            
            <div class="mb-4">
                <label for="title" class="block text-sm font-medium text-gray-300 mb-2">
                    Название события <span class="text-red-500">*</span>
                </label>
                <input type="text" name="title" id="title" value="{{ old('title') }}" placeholder="Название события" required
                    class="w-full px-4 py-2 bg-[#121212] border border-gray-700 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-[#a78bfa] focus:border-transparent">
            </div>
            
            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label for="city" class="block text-sm font-medium text-gray-300 mb-2">
                        Город <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="city" id="city" value="{{ old('city') }}" placeholder="Город проведения" required
                        class="w-full px-4 py-2 bg-[#121212] border border-gray-700 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-[#a78bfa] focus:border-transparent">
                </div>
                <div>
                    <label for="date" class="block text-sm font-medium text-gray-300 mb-2">
                        Дата проведения <span class="text-red-500">*</span>
                    </label>
                    <input type="date" name="date" id="date" value="{{ old('date') }}" required
                        class="w-full px-4 py-2 bg-[#121212] border border-gray-700 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-[#a78bfa] focus:border-transparent">
                </div>
            </div>
            
            <div class="mb-4">
                <label for="cover" class="block text-sm font-medium text-gray-300 mb-2">
                    Обложка
                </label>
                <div class="mb-3">
                    <label class="flex items-center space-x-2 cursor-pointer">
                        <input 
                            type="checkbox" 
                            name="create_without_cover" 
                            id="create_without_cover"
                            value="1"
                            class="w-4 h-4 text-[#a78bfa] bg-[#121212] border-gray-700 rounded focus:ring-[#a78bfa]"
                        >
                        <span class="text-sm text-gray-300">Создать событие без обложки (для отложенной загрузки фотографий)</span>
                    </label>
                    <p class="mt-1 text-xs text-gray-500">Если отмечено, событие будет создано без обложки. Вы сможете загрузить фотографии позже по ссылке события.</p>
                </div>
                <div class="flex items-center gap-4" id="cover-upload-section">
                    <input 
                        type="file" 
                        name="cover" 
                        id="cover"
                        accept="image/jpeg,image/jpg,image/png" 
                        style="display: none;"
                    >
                    <button 
                        type="button" 
                        id="cover-button"
                        class="px-4 py-2 bg-[#a78bfa] text-white rounded-lg hover:bg-[#8b5cf6] transition-colors"
                    >
                        Выбрать файл
                    </button>
                    <span id="cover-selected-name" class="text-[#a78bfa] text-sm"></span>
                </div>
                <p class="mt-1 text-sm text-gray-400">Будет автоматически обработана с добавлением логотипа и текста. Максимальный размер: 10 МБ. Форматы: JPEG, PNG</p>
                <p id="cover-filename" class="mt-2 text-sm" style="color: #10b981;"></p>
                @error('cover')
                    <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                @enderror
            </div>
            
            <div class="mb-4">
                <label for="description" class="block text-sm font-medium text-gray-300 mb-2">
                    Описание
                </label>
                <textarea name="description" id="description" rows="4" placeholder="Описание события (необязательно)"
                    class="w-full px-4 py-2 bg-[#121212] border border-gray-700 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-[#a78bfa] focus:border-transparent resize-none">{{ old('description') }}</textarea>
            </div>
            
            @if($errors->any())
                <div class="mb-4 p-4 bg-red-900/20 border border-red-500 rounded-lg">
                    <ul class="list-disc list-inside text-red-400">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            
            <div class="flex space-x-3">
                <button type="submit" class="px-4 py-2 bg-[#a78bfa] text-white rounded-lg hover:bg-[#8b5cf6] transition-colors">
                    Создать событие
                </button>
                <a href="{{ route('photo.events.index') }}" class="px-4 py-2 border-2 border-[#a78bfa] text-[#a78bfa] rounded-lg hover:bg-[#a78bfa] hover:text-white transition-colors">
                    Отмена
                </a>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
<script>
(function() {
    'use strict';
    
    function init() {
        const coverInput = document.getElementById('cover');
        const coverButton = document.getElementById('cover-button');
        const coverSelectedName = document.getElementById('cover-selected-name');
        const filenameDisplay = document.getElementById('cover-filename');
        
        if (!coverInput || !coverButton) {
            console.error('Cover input or button not found!');
            return;
        }
        
        // Обработчик клика на кнопку
        coverButton.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            console.log('[COVER] Button clicked, triggering file input');
            coverInput.click();
        }, false);
        
        // Обработчик изменения файла
        const handleFileChange = function(e) {
            console.log('[COVER] ===== CHANGE EVENT =====');
            console.log('[COVER] Event:', e);
            console.log('[COVER] Target:', e.target);
            console.log('[COVER] Files:', e.target.files);
            console.log('[COVER] Value:', e.target.value);
            
            setTimeout(function() {
                const files = e.target.files;
                console.log('[COVER] Files after timeout:', files);
                
                if (files && files.length > 0) {
                    const file = files[0];
                    console.log('[COVER] ✓ File:', file.name, file.size, file.type);
                    
                    // Обновляем UI
                    if (coverButton) {
                        coverButton.textContent = 'Файл выбран';
                        coverButton.classList.remove('bg-[#a78bfa]');
                        coverButton.classList.add('bg-green-600');
                    }
                    if (coverSelectedName) {
                        coverSelectedName.textContent = file.name + ' (' + (file.size / 1024 / 1024).toFixed(2) + ' МБ)';
                    }
                    if (filenameDisplay) {
                        filenameDisplay.textContent = '✓ Выбран: ' + file.name;
                        filenameDisplay.style.color = '#10b981';
                    }
                } else {
                    console.warn('[COVER] ❌ No files!');
                }
            }, 100);
        };
        
        // Множественные обработчики
        coverInput.addEventListener('change', handleFileChange, false);
        coverInput.onchange = handleFileChange;
        
        // Polling для проверки
        let lastValue = '';
        setInterval(function() {
            if (coverInput.value !== lastValue) {
                console.log('[COVER] Value changed via polling:', coverInput.value);
                lastValue = coverInput.value;
                if (coverInput.files && coverInput.files.length > 0) {
                    handleFileChange({target: coverInput});
                }
            }
        }, 300);
        
        console.log('[COVER] Initialized');
        
        // Обработка чекбокса "Создать без обложки"
        const createWithoutCoverCheckbox = document.getElementById('create_without_cover');
        const coverUploadSection = document.getElementById('cover-upload-section');
        const coverInput = document.getElementById('cover');
        
        if (createWithoutCoverCheckbox && coverUploadSection) {
            createWithoutCoverCheckbox.addEventListener('change', function() {
                if (this.checked) {
                    coverUploadSection.style.display = 'none';
                    coverInput.removeAttribute('required');
                    if (coverInput.files && coverInput.files.length > 0) {
                        coverInput.value = '';
                        if (coverButton) {
                            coverButton.textContent = 'Выбрать файл';
                            coverButton.classList.remove('bg-green-600');
                            coverButton.classList.add('bg-[#a78bfa]');
                        }
                        if (coverSelectedName) coverSelectedName.textContent = '';
                        if (filenameDisplay) filenameDisplay.textContent = '';
                    }
                } else {
                    coverUploadSection.style.display = 'flex';
                    coverInput.setAttribute('required', 'required');
                }
            });
        }
    }
    
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
</script>
@endpush


