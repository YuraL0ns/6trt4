@extends('layouts.app')

@section('title', 'Редактировать событие - Hunter-Photo.Ru')
@section('page-title', 'Редактировать событие')

@section('content')
    <x-card>
        <form action="{{ route('admin.events.update', $event->id) }}" method="POST">
            @csrf
            @method('PUT')
            
            <x-input label="Название события" name="title" value="{{ old('title', $event->title) }}" required />
            
            <div class="grid grid-cols-2 gap-4">
                <x-input label="Город" name="city" value="{{ old('city', $event->city) }}" required />
                <x-input label="Дата проведения" name="date" type="date" value="{{ old('date', $event->date->format('Y-m-d')) }}" required />
            </div>
            
            <x-input label="Цена за фотографию (₽)" name="price" type="number" min="0" step="0.01" value="{{ old('price', $event->price) }}" required />
            
            <x-select 
                label="Статус" 
                name="status" 
                :options="[
                    'draft' => 'Черновик',
                    'processing' => 'В обработке',
                    'published' => 'Опубликовано',
                    'completed' => 'Завершено'
                ]"
                :value="old('status', $event->status)"
                required 
            />
            
            <x-textarea label="Описание" name="description" rows="4" value="{{ old('description', $event->description) }}" />
            
            @if($errors->any())
                <x-alert type="error" class="mb-4">
                    <ul class="list-disc list-inside">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </x-alert>
            @endif
            
            <div class="flex space-x-3">
                <x-button type="submit">Сохранить</x-button>
                <x-button href="{{ route('admin.events.index') }}" variant="outline" type="button">Отмена</x-button>
            </div>
        </form>
    </x-card>
@endsection


