<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Avatar\AvatarGeneratorService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreateAdminCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'admin:create 
                            {--email= : Email администратора}
                            {--password= : Пароль администратора}
                            {--first-name= : Имя}
                            {--last-name= : Фамилия}
                            {--second-name= : Отчество}
                            {--phone= : Телефон}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Создать администратора системы';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Создание администратора...');
        $this->newLine();

        // Собираем данные
        $data = $this->gatherData();

        // Валидация
        $validator = Validator::make($data, [
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:8',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'second_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            $this->error('Ошибки валидации:');
            foreach ($validator->errors()->all() as $error) {
                $this->error('  - ' . $error);
            }
            return Command::FAILURE;
        }

        // Генерируем аватар
        $avatarGenerator = app(AvatarGeneratorService::class);
        $avatarPath = $avatarGenerator->generate(
            $data['first_name'],
            $data['last_name']
        );

        // Создаем администратора
        try {
            $admin = User::create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'second_name' => $data['second_name'] ?? null,
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => Hash::make($data['password']),
                'group' => 'admin',
                'status' => 'active',
                'avatar' => $avatarPath,
            ]);

            $this->info('✅ Администратор успешно создан!');
            $this->newLine();
            $this->table(
                ['Поле', 'Значение'],
                [
                    ['ID', $admin->id],
                    ['Имя', $admin->first_name],
                    ['Фамилия', $admin->last_name],
                    ['Отчество', $admin->second_name ?? '-'],
                    ['Email', $admin->email],
                    ['Телефон', $admin->phone ?? '-'],
                    ['Группа', $admin->group],
                    ['Статус', $admin->status],
                ]
            );

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Ошибка при создании администратора: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Собрать данные от пользователя
     */
    protected function gatherData(): array
    {
        $data = [];

        // Email
        $data['email'] = $this->option('email') ?? $this->ask('Email администратора');

        // Проверка существования
        if (User::where('email', $data['email'])->exists()) {
            if (!$this->confirm('Пользователь с таким email уже существует. Продолжить?', false)) {
                $this->info('Отменено.');
                exit(Command::FAILURE);
            }
        }

        // Пароль
        $data['password'] = $this->option('password') ?? $this->secret('Пароль (минимум 8 символов)');
        
        if (strlen($data['password']) < 8) {
            $this->error('Пароль должен содержать минимум 8 символов');
            exit(Command::FAILURE);
        }

        // Подтверждение пароля
        if (!$this->option('password')) {
            $passwordConfirm = $this->secret('Подтвердите пароль');
            if ($data['password'] !== $passwordConfirm) {
                $this->error('Пароли не совпадают');
                exit(Command::FAILURE);
            }
        }

        // Имя
        $data['first_name'] = $this->option('first-name') ?? $this->ask('Имя');

        // Фамилия
        $data['last_name'] = $this->option('last-name') ?? $this->ask('Фамилия');

        // Отчество
        $data['second_name'] = $this->option('second-name');
        if (!$data['second_name']) {
            $data['second_name'] = $this->ask('Отчество (необязательно)', null);
        }

        // Телефон
        $data['phone'] = $this->option('phone');
        if (!$data['phone']) {
            $data['phone'] = $this->ask('Телефон (необязательно)', null);
        }

        return $data;
    }
}
