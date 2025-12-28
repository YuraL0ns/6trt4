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
        Schema::create('face_searches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('photo_id');
            $table->string('user_uploaded_photo_path'); // Путь к загруженному селфи
            $table->decimal('similarity_score', 5, 2); // Оценка схожести
            $table->timestamps();
            
            $table->foreign('photo_id')->references('id')->on('photos')->onDelete('cascade');
            $table->index('photo_id');
            $table->index('similarity_score');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('face_searches');
    }
};
