<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\Avatar\AvatarGeneratorService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    protected AvatarGeneratorService $avatarService;

    public function __construct()
    {
        $this->avatarService = new AvatarGeneratorService();
    }

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Администратор
        $admin = User::create([
            'first_name' => 'Администратор',
            'last_name' => 'Системы',
            'second_name' => null,
            'login' => 'admin',
            'email' => 'admin@hunter-photo.ru',
            'phone' => '+7 (999) 123-45-67',
            'password' => Hash::make('password'),
            'city' => 'Москва',
            'gender' => 'male',
            'group' => 'admin',
            'balance' => 0,
            'status' => 'active',
        ]);
        
        try {
            $avatarPath = $this->avatarService->generate($admin->first_name, $admin->last_name);
            $admin->update(['avatar' => $avatarPath]);
        } catch (\Exception $e) {
            // Игнорируем ошибки генерации аватара
        }

        // Фотографы
        $photographers = [
            ['first_name' => 'Иван', 'last_name' => 'Петров', 'second_name' => 'Сергеевич', 'login' => 'ivan_photo', 'email' => 'ivan@example.com', 'city' => 'Москва'],
            ['first_name' => 'Мария', 'last_name' => 'Сидорова', 'second_name' => 'Александровна', 'login' => 'maria_photo', 'email' => 'maria@example.com', 'city' => 'Санкт-Петербург'],
            ['first_name' => 'Алексей', 'last_name' => 'Козлов', 'second_name' => 'Владимирович', 'login' => 'alex_photo', 'email' => 'alex@example.com', 'city' => 'Казань'],
        ];

        foreach ($photographers as $photo) {
            $photographer = User::create([
                'first_name' => $photo['first_name'],
                'last_name' => $photo['last_name'],
                'second_name' => $photo['second_name'],
                'login' => $photo['login'],
                'email' => $photo['email'],
                'phone' => '+7 (999) ' . rand(100, 999) . '-' . rand(10, 99) . '-' . rand(10, 99),
                'password' => Hash::make('password'),
                'city' => $photo['city'],
                'gender' => rand(0, 1) ? 'male' : 'female',
                'group' => 'photo',
                'balance' => rand(1000, 50000),
                'status' => 'active',
                'hash_login' => $photo['login'] . '-' . Str::random(5),
                'description' => 'Профессиональный фотограф с многолетним опытом работы на спортивных мероприятиях.',
            ]);
            
            try {
                $avatarPath = $this->avatarService->generate($photographer->first_name, $photographer->last_name);
                $photographer->update(['avatar' => $avatarPath]);
            } catch (\Exception $e) {
                // Игнорируем ошибки генерации аватара
            }
        }

        // Обычные пользователи
        $users = [
            ['first_name' => 'Дмитрий', 'last_name' => 'Иванов', 'second_name' => 'Петрович', 'login' => 'dmitry', 'email' => 'dmitry@example.com', 'city' => 'Москва'],
            ['first_name' => 'Анна', 'last_name' => 'Смирнова', 'second_name' => 'Ивановна', 'login' => 'anna', 'email' => 'anna@example.com', 'city' => 'Санкт-Петербург'],
            ['first_name' => 'Сергей', 'last_name' => 'Волков', 'second_name' => 'Андреевич', 'login' => 'sergey', 'email' => 'sergey@example.com', 'city' => 'Новосибирск'],
            ['first_name' => 'Елена', 'last_name' => 'Новикова', 'second_name' => 'Дмитриевна', 'login' => 'elena', 'email' => 'elena@example.com', 'city' => 'Екатеринбург'],
            ['first_name' => 'Павел', 'last_name' => 'Морозов', 'second_name' => 'Сергеевич', 'login' => 'pavel', 'email' => 'pavel@example.com', 'city' => 'Краснодар'],
        ];

        foreach ($users as $userData) {
            $user = User::create([
                'first_name' => $userData['first_name'],
                'last_name' => $userData['last_name'],
                'second_name' => $userData['second_name'],
                'login' => $userData['login'],
                'email' => $userData['email'],
                'phone' => '+7 (999) ' . rand(100, 999) . '-' . rand(10, 99) . '-' . rand(10, 99),
                'password' => Hash::make('password'),
                'city' => $userData['city'],
                'gender' => rand(0, 1) ? 'male' : 'female',
                'group' => 'user',
                'balance' => 0,
                'status' => 'active',
            ]);
            
            try {
                $avatarPath = $this->avatarService->generate($user->first_name, $user->last_name);
                $user->update(['avatar' => $avatarPath]);
            } catch (\Exception $e) {
                // Игнорируем ошибки генерации аватара
            }
        }

        // Заблокированный пользователь
        $banned = User::create([
            'first_name' => 'Заблокированный',
            'last_name' => 'Пользователь',
            'second_name' => null,
            'login' => 'banned_user',
            'email' => 'banned@example.com',
            'phone' => '+7 (999) 999-99-99',
            'password' => Hash::make('password'),
            'city' => 'Москва',
            'gender' => 'male',
            'group' => 'blocked',
            'balance' => 0,
            'status' => 'blocked',
        ]);
        
        try {
            $avatarPath = $this->avatarService->generate($banned->first_name, $banned->last_name);
            $banned->update(['avatar' => $avatarPath]);
        } catch (\Exception $e) {
            // Игнорируем ошибки генерации аватара
        }
    }
}
