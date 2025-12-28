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
        Schema::create('photographer_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('photographer_id');
            $table->uuid('user_id');
            $table->text('message');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            
            $table->foreign('photographer_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index('photographer_id');
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('photographer_messages');
    }
};
