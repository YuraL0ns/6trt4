<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('first_name'); // Имя
            $table->string('last_name'); // Фамилия
            $table->string('second_name')->nullable(); // Отчество
            $table->string('login')->unique()->nullable(); // Логин для входа
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('password');
            $table->string('avatar')->nullable(); // Путь к аватару
            $table->string('city')->nullable();
            $table->enum('gender', ['male', 'female'])->nullable();
            $table->enum('group', ['user', 'photo', 'admin', 'blocked'])->default('user');
            $table->decimal('balance', 10, 2)->default(0); // Баланс для фотографов
            $table->enum('status', ['active', 'blocked'])->default('active');
            $table->string('hash_login')->nullable()->unique(); // Для фотографов (yuralons-1q2w3)
            $table->text('description')->nullable(); // Описание для фотографов
            $table->rememberToken();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamps();
            
            $table->index('email');
            $table->index('login');
            $table->index('phone');
            $table->index('group');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
