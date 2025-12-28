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
        Schema::create('photos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('event_id');
            $table->decimal('price', 10, 2);
            $table->boolean('has_faces')->default(false); // Объединено из add_has_faces_to_photos_table
            $table->string('original_path'); // Путь к оригиналу (без exif)
            $table->string('custom_path')->nullable(); // Путь к webp с watermark (nullable из make_custom_path_nullable)
            $table->json('face_vec')->nullable(); // Вектора лиц
            $table->json('face_encodings')->nullable(); // Кодировки лиц
            $table->json('numbers')->nullable(); // Найденные номера
            $table->json('exif_data')->nullable(); // EXIF данные (объединено из add_exif_data_to_photos_table)
            $table->string('created_at_exif', 50)->nullable(); // Дата создания из EXIF (объединено из add_exif_data_to_photos_table)
            $table->timestamp('date_exif')->nullable(); // Дата из EXIF
            // Статус с включением 'pending' (объединено из make_custom_path_nullable)
            $table->enum('status', ['pending', 'processing', 'done', 'error'])->default('pending');
            $table->string('original_name'); // Оригинальное имя файла
            $table->string('custom_name')->nullable(); // Имя файла после обработки (nullable из make_custom_path_nullable)
            $table->string('s3_custom_url')->nullable(); // URL на S3 для custom
            $table->string('s3_original_url')->nullable(); // URL на S3 для original
            $table->timestamps();
            
            $table->foreign('event_id')->references('id')->on('events')->onDelete('cascade');
            $table->index('event_id');
            $table->index('status');
            $table->index('date_exif');
        });
        
        // Для PostgreSQL: обновляем check constraint после создания enum
        try {
            $constraints = DB::select("
                SELECT tc.constraint_name 
                FROM information_schema.table_constraints tc
                JOIN information_schema.constraint_column_usage ccu 
                    ON tc.constraint_name = ccu.constraint_name
                    AND tc.table_schema = ccu.table_schema
                WHERE tc.table_name = 'photos' 
                AND tc.constraint_type = 'CHECK'
                AND ccu.column_name = 'status'
                AND tc.table_schema = 'public'
            ");
            
            foreach ($constraints as $constraint) {
                $constraintName = $constraint->constraint_name;
                DB::statement("ALTER TABLE photos DROP CONSTRAINT IF EXISTS \"{$constraintName}\"");
            }
            
            // Добавляем check constraint с включением 'pending'
            DB::statement("ALTER TABLE photos ADD CONSTRAINT photos_status_check CHECK (status IN ('pending', 'processing', 'done', 'error'))");
        } catch (\Exception $e) {
            // Если constraint не найден или уже правильный, продолжаем
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('photos');
    }
};
