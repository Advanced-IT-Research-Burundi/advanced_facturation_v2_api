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
        Schema::create('bank_deposits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cash_register_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->decimal('amount', 15, 2);
            $table->date('deposit_date');
            $table->string('bank_name');
            $table->string('account_name')->nullable();
            $table->string('account_number')->nullable();
            $table->string('reference')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'deposit_date']);
            $table->index(['cash_register_id', 'deposit_date']);
        });

        Schema::table('cash_movements', function (Blueprint $table) {
            $table->foreignId('bank_deposit_id')
                ->nullable()
                ->after('depense_id')
                ->constrained('bank_deposits')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cash_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bank_deposit_id');
        });

        Schema::dropIfExists('bank_deposits');
    }
};
