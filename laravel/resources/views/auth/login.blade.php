@extends('layouts.app')

@section('title', 'Вход - Hunter-Photo.Ru')
@section('page-title', 'Вход в систему')

@section('content')
    <div class="max-w-md mx-auto">
        <x-card title="Вход в систему">
            <form method="POST" action="{{ route('login') }}">
                @csrf

                <x-input 
                    label="Email или логин" 
                    name="login" 
                    type="text" 
                    placeholder="email@example.com"
                    value="{{ old('login') }}"
                    required 
                />

                <x-input 
                    label="Пароль" 
                    name="password" 
                    type="password" 
                    placeholder="Введите пароль"
                    required 
                />

                <x-checkbox 
                    label="Запомнить меня" 
                    name="remember" 
                    checked="{{ old('remember') ? true : false }}"
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

                <div class="flex items-center justify-between">
                    <x-button type="submit" class="w-full">
                        Войти
                    </x-button>
                </div>

                <div class="mt-4 text-center space-x-4">
                    @if (Route::has('password.request'))
                        <a href="{{ route('password.request') }}" class="text-sm text-[#a78bfa] hover:text-[#8b5cf6]">
                            Забыли пароль?
                        </a>
                    @endif
                    <a href="{{ route('register') }}" class="text-sm text-[#a78bfa] hover:text-[#8b5cf6]">
                        Регистрация
                    </a>
                </div>
            </form>
        </x-card>
    </div>
@endsection
