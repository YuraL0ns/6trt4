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
        Schema::create('withdrawals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('photographer_id');
            $table->decimal('amount', 10, 2);
            $table->enum('type', ['individual', 'legal']); // физ.лицо или юр.лицо
            $table->enum('status', ['pending', 'approved', 'rejected', 'completed'])->default('pending');
            
            // Для юр.лица
            $table->string('inn')->nullable();
            $table->string('kpp')->nullable();
            $table->string('account')->nullable();
            $table->string('bank')->nullable();
            $table->string('organization_name')->nullable();
            
            // Для физ.лица
            $table->enum('transfer_type', ['sbp', 'card', 'account'])->nullable(); // СБП, карта, лицевой счет
            $table->string('phone')->nullable(); // Для СБП
            $table->string('card_number')->nullable(); // Для карты
            $table->string('account_number')->nullable(); // Для лицевого счета
            $table->string('bank_name')->nullable(); // Наименование банка
            $table->text('account_comment')->nullable(); // Комментарий для лицевого счета
            
            $table->string('receipt_path_admin')->nullable(); // Чек от администратора
            $table->string('receipt_path_photographer')->nullable(); // Чек от фотографа
            
            $table->decimal('balance_before', 10, 2)->nullable(); // Баланс до вывода
            $table->decimal('balance_after', 10, 2)->nullable(); // Баланс после вывода
            
            // Поля для налогов (объединено из add_tax_fields_to_withdrawals_table)
            $table->decimal('tax_percent', 5, 2)->nullable()->comment('Процент налога с вывода');
            $table->decimal('tax_amount', 10, 2)->nullable()->comment('Сумма налога');
            $table->decimal('final_amount', 10, 2)->nullable()->comment('Финальная сумма к выводу (после вычета налога)');
            
            $table->timestamps();
            
            $table->foreign('photographer_id')->references('id')->on('users')->onDelete('cascade');
            $table->index('photographer_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('withdrawals');
    }
};
