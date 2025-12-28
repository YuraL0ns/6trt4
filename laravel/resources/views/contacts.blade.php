@extends('layouts.app')

@section('title', 'Контакты - Hunter-Photo.Ru')
@section('page-title', 'Контакты')

@section('content')
    <x-card title="Свяжитесь с нами">
        <form class="space-y-4">
            <x-input label="Имя" name="name" placeholder="Ваше имя" required />
            <x-input label="Email" name="email" type="email" placeholder="email@example.com" required />
            <x-input label="Тема" name="subject" placeholder="Тема сообщения" required />
            <x-textarea label="Сообщение" name="message" rows="6" placeholder="Ваше сообщение" required />
            <x-button type="submit">Отправить</x-button>
        </form>
    </x-card>
@endsection


