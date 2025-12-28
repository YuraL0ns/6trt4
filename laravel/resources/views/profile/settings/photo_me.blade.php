@extends('layouts.app')

@section('title', 'Стать фотографом - Hunter-Photo.Ru')
@section('page-title', 'Заявка на смену группы')

@section('content')
    <x-card>
        <p class="text-gray-300 mb-6">
            Заполните форму, чтобы подать заявку на смену группы с пользователя на фотографа. 
            После рассмотрения заявки администратором, вы получите доступ к функциям фотографа.
        </p>
        
        <form action="{{ route('profile.settings.photo_me.store') }}" method="POST" class="space-y-4">
            @csrf
            
            <x-textarea 
                label="Причина смены группы" 
                name="reason" 
                rows="8" 
                placeholder="Опишите, почему вы хотите стать фотографом на нашей платформе. Расскажите о своем опыте, оборудовании и планах..."
                required 
            />
            
            @if($errors->any())
                <x-alert type="error">
                    <ul class="list-disc list-inside">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </x-alert>
            @endif
            
            <div class="flex space-x-3">
                <x-button type="submit">Отправить заявку</x-button>
                <x-button href="{{ route('home') }}" variant="outline" type="button">Отмена</x-button>
            </div>
        </form>
    </x-card>
@endsection


