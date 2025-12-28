@extends('layouts.app')

@section('title', 'Регистрация - Hunter-Photo.Ru')
@section('page-title', 'Регистрация')

@section('content')
    <div class="max-w-md mx-auto">
        <x-card title="Регистрация">
            <form method="POST" action="{{ route('register') }}">
                @csrf

                <div class="grid grid-cols-2 gap-4">
                    <x-input 
                        label="Имя" 
                        name="first_name" 
                        placeholder="Имя"
                        value="{{ old('first_name') }}"
                        required 
                    />

                    <x-input 
                        label="Фамилия" 
                        name="last_name" 
                        placeholder="Фамилия"
                        value="{{ old('last_name') }}"
                        required 
                    />
                </div>

                <x-input 
                    label="Отчество" 
                    name="second_name" 
                    placeholder="Отчество (необязательно)"
                    value="{{ old('second_name') }}"
                />

                <x-input 
                    label="Email" 
                    name="email" 
                    type="email" 
                    placeholder="email@example.com"
                    value="{{ old('email') }}"
                    required 
                />

                <x-input 
                    label="Логин" 
                    name="login" 
                    type="text" 
                    placeholder="Ваш логин (необязательно)"
                    value="{{ old('login') }}"
                />

                <x-input 
                    label="Телефон" 
                    name="phone" 
                    type="tel" 
                    placeholder="+7 (999) 999-99-99"
                    value="{{ old('phone') }}"
                />

                <x-input 
                    label="Пароль" 
                    name="password" 
                    type="password" 
                    placeholder="Минимум 8 символов"
                    required 
                />

                <x-input 
                    label="Подтверждение пароля" 
                    name="password_confirmation" 
                    type="password" 
                    placeholder="Повторите пароль"
                    required 
                />

                @if($errors->any())
                    <x-alert type="error" class="mb-4">
                        <ul class="list-disc list-inside">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </x-alert>
                @endif

                <x-button type="submit" class="w-full">
                    Зарегистрироваться
                </x-button>

                <div class="mt-4 text-center">
                    <a href="{{ route('login') }}" class="text-sm text-[#a78bfa] hover:text-[#8b5cf6]">
                        Уже есть аккаунт? Войти
                    </a>
                </div>
            </form>
        </x-card>
    </div>
@endsection
