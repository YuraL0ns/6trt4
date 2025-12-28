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
        Schema::create('pages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('page_title');
            $table->text('page_meta_descr')->nullable();
            $table->text('page_meta_key')->nullable();
            $table->longText('page_content'); // HTML содержимое
            $table->string('page_url')->unique(); // URL страницы (например /about)
            $table->timestamps();
            
            $table->index('page_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};
