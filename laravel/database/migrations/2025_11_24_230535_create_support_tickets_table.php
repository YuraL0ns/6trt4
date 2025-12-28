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
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('admin_id')->nullable();
            $table->string('subject'); // Тема обращения
            $table->enum('type', ['technical', 'payment', 'photographer', 'other'])->default('technical');
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->uuid('last_replied_by')->nullable(); // Кто последний ответил
            $table->timestamps();
            
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('admin_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('last_replied_by')->references('id')->on('users')->onDelete('set null');
            $table->index('user_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('support_tickets');
    }
};
