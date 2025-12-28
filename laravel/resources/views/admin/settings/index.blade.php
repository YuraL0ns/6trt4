@extends('layouts.app')

@section('title', 'Настройки - Hunter-Photo.Ru')
@section('page-title', 'Настройки системы')

@section('content')
    <x-card>
        <form action="{{ route('admin.settings.update') }}" method="POST" enctype="multipart/form-data">
            @csrf
            
            <h3 class="text-lg font-semibold text-white mb-4">Основные настройки</h3>
            <div class="space-y-4 mb-6">
                <x-input label="Название сайта" name="site_title" value="{{ $settings['site_title']->value ?? '' }}" />
                <x-textarea label="Описание сайта" name="site_description" rows="3" value="{{ $settings['site_description']->value ?? '' }}" />
            </div>
            
            <h3 class="text-lg font-semibold text-white mb-4">Комиссии</h3>
            <div class="grid grid-cols-2 gap-4 mb-6">
                <x-input label="Комиссия с продажи (%)" name="percent_for_sales" type="number" min="0" max="100" step="0.01" value="{{ $settings['percent_for_sales']->value ?? 20 }}" />
                <x-input label="Налог с вывода (%)" name="percent_for_salary" type="number" min="0" max="100" step="0.01" value="{{ $settings['percent_for_salary']->value ?? 6 }}" />
            </div>
            
            <h3 class="text-lg font-semibold text-white mb-4">Файлы</h3>
            <div class="space-y-4 mb-6">
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Логотип сайта</label>
                    <input type="file" name="site_logo" accept="image/*" class="w-full px-4 py-2 bg-[#121212] border border-gray-700 rounded-lg text-white">
                    @if(isset($settings['site_logo']) && $settings['site_logo']->value)
                        <p class="text-sm text-gray-400 mt-1">Текущий: {{ $settings['site_logo']->value }}</p>
                    @endif
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Favicon</label>
                    <input type="file" name="site_favicon" accept="image/*" class="w-full px-4 py-2 bg-[#121212] border border-gray-700 rounded-lg text-white">
                    @if(isset($settings['site_favicon']) && $settings['site_favicon']->value)
                        <p class="text-sm text-gray-400 mt-1">Текущий: {{ $settings['site_favicon']->value }}</p>
                    @endif
                </div>
            </div>
            
            <h3 class="text-lg font-semibold text-white mb-4">SEO</h3>
            <div class="space-y-4 mb-6">
                <x-textarea label="Meta Description" name="meta_description" rows="2" value="{{ $settings['meta_description']->value ?? '' }}" />
                <x-textarea label="Meta Keywords" name="meta_keywords" rows="2" value="{{ $settings['meta_keywords']->value ?? '' }}" />
                <x-input label="Meta Author" name="meta_author" value="{{ $settings['meta_author']->value ?? '' }}" />
                <x-input label="Canonical URL" name="meta_canonical" value="{{ $settings['meta_canonical']->value ?? '' }}" />
            </div>
            
            @if($errors->any())
                <x-alert type="error" class="mb-4">
                    <ul class="list-disc list-inside">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </x-alert>
            @endif
            
            <x-button type="submit" class="w-full">Сохранить настройки</x-button>
        </form>
    </x-card>
@endsection


