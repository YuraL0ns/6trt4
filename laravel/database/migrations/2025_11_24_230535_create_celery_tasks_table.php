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
        Schema::create('celery_tasks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('task_id')->nullable(); // ID задачи в Celery (не уникальный, так как одна задача обрабатывает все типы анализов)
            $table->uuid('event_id');
            $table->string('task_type'); // timeline, remove_exif, watermark, face_search, number_search
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->integer('progress')->default(0); // Прогресс в процентах
            $table->text('error_message')->nullable();
            $table->timestamps();
            
            $table->foreign('event_id')->references('id')->on('events')->onDelete('cascade');
            $table->index('event_id');
            $table->index('status');
            $table->index('task_id'); // Индекс для быстрого поиска по task_id, но не уникальный
            // Составной уникальный индекс: одна задача одного типа для одного события
            $table->unique(['event_id', 'task_type'], 'celery_tasks_event_task_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('celery_tasks');
    }
};
