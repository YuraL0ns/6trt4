@extends('layouts.app')

@section('title', 'Редактирование профиля - Hunter-Photo.Ru')
@section('page-title', 'Редактирование профиля')

@section('content')
    <div class="max-w-2xl mx-auto">
        <x-card title="Редактирование профиля">
            <form method="POST" action="{{ route('profile.update') }}">
                @csrf
                @method('PUT')

                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <p class="text-sm text-gray-400 mb-2">Имя (нельзя изменить)</p>
                        <x-input 
                            name="first_name" 
                            value="{{ $user->first_name }}"
                            disabled
                        />
                    </div>
                    <div>
                        <p class="text-sm text-gray-400 mb-2">Логин (нельзя изменить)</p>
                        <x-input 
                            name="login" 
                            value="{{ $user->login ?? 'Не указан' }}"
                            disabled
                        />
                    </div>
                </div>

                <x-input 
                    label="Фамилия" 
                    name="last_name" 
                    placeholder="Фамилия"
                    value="{{ old('last_name', $user->last_name) }}"
                    required 
                />

                <x-input 
                    label="Отчество" 
                    name="second_name" 
                    placeholder="Отчество (необязательно)"
                    value="{{ old('second_name', $user->second_name) }}"
                />

                <x-input 
                    label="Email" 
                    name="email" 
                    type="email" 
                    placeholder="email@example.com"
                    value="{{ old('email', $user->email) }}"
                    required 
                />

                <x-input 
                    label="Телефон" 
                    name="phone" 
                    type="tel" 
                    placeholder="+7 (999) 999-99-99"
                    value="{{ old('phone', $user->phone) }}"
                />

                <x-input 
                    label="Город" 
                    name="city" 
                    placeholder="Город"
                    value="{{ old('city', $user->city) }}"
                />

                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Пол</label>
                    <select name="gender" class="w-full px-4 py-2 bg-[#1a1a1a] border border-gray-700 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-[#a78bfa]">
                        <option value="">Не указан</option>
                        <option value="male" {{ old('gender', $user->gender) === 'male' ? 'selected' : '' }}>Мужской</option>
                        <option value="female" {{ old('gender', $user->gender) === 'female' ? 'selected' : '' }}>Женский</option>
                    </select>
                </div>

                <div class="border-t border-gray-700 pt-4 mt-4">
                    <h3 class="text-lg font-semibold text-white mb-4">Изменить пароль</h3>
                    <p class="text-sm text-gray-400 mb-4">Оставьте пустым, если не хотите менять пароль</p>
                    
                    <x-input 
                        label="Новый пароль" 
                        name="password" 
                        type="password" 
                        placeholder="Минимум 8 символов"
                    />

                    <x-input 
                        label="Подтверждение пароля" 
                        name="password_confirmation" 
                        type="password" 
                        placeholder="Повторите пароль"
                    />
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

                <div class="flex space-x-3 mt-6">
                    <x-button type="submit" class="flex-1">
                        Сохранить изменения
                    </x-button>
                    <x-button href="{{ route('profile.index') }}" variant="outline" type="button">
                        Отмена
                    </x-button>
                </div>
            </form>
        </x-card>
    </div>
@endsection

