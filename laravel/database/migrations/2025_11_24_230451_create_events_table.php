<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('author_id'); // Фотограф
            $table->string('title'); // Название события
            $table->string('slug')->nullable()->unique(); // Slug для URL (объединено из add_slug_to_events_table)
            $table->string('city'); // Город проведения
            $table->date('date'); // Дата проведения
            $table->string('cover_path')->nullable(); // Путь к обложке
            $table->decimal('price', 10, 2); // Цена за фотографию
            // Статус с включением 'archived' (объединено из add_archived_status_to_events_table)
            $table->enum('status', ['draft', 'processing', 'published', 'completed', 'archived'])->default('draft');
            $table->text('description')->nullable();
            $table->timestamps();
            
            $table->foreign('author_id')->references('id')->on('users')->onDelete('cascade');
            $table->index('author_id');
            $table->index('status');
            $table->index('date');
            $table->index('slug');
        });
        
        // Для PostgreSQL: обновляем check constraint после создания enum
        // Laravel создает enum, но также может создать check constraint
        // Убеждаемся, что constraint включает все значения включая 'archived'
        try {
            $constraints = DB::select("
                SELECT tc.constraint_name 
                FROM information_schema.table_constraints tc
                JOIN information_schema.constraint_column_usage ccu 
                    ON tc.constraint_name = ccu.constraint_name
                    AND tc.table_schema = ccu.table_schema
                WHERE tc.table_name = 'events' 
                AND tc.constraint_type = 'CHECK'
                AND ccu.column_name = 'status'
                AND tc.table_schema = 'public'
            ");
            
            foreach ($constraints as $constraint) {
                $constraintName = $constraint->constraint_name;
                DB::statement("ALTER TABLE events DROP CONSTRAINT IF EXISTS \"{$constraintName}\"");
            }
            
            // Добавляем check constraint с включением 'archived'
            DB::statement("ALTER TABLE events ADD CONSTRAINT events_status_check CHECK (status IN ('draft', 'processing', 'published', 'completed', 'archived'))");
        } catch (\Exception $e) {
            // Если constraint не найден или уже правильный, продолжаем
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
