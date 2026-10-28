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
        Schema::create('cash_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('cash_register_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('to_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('amount', 15, 2);
            $table->date('transfer_date');
            $table->enum('status', ['pending', 'accepted', 'rejected', 'cancelled'])->default('pending');
            $table->string('note')->nullable();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable();
            $table->foreignId('out_movement_id')->nullable()->constrained('cash_movements')->nullOnDelete();
            $table->foreignId('in_movement_id')->nullable()->constrained('cash_movements')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'transfer_date', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_transfers');
    }
};
