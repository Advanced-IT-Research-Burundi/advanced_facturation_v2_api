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
        Schema::create('customer_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->onDelete('cascade');
            $table->foreignId('customer_id')->constrained();
            $table->foreignId('company_id')->constrained();
            $table->enum('type', ['email', 'sms', 'phone', 'letter', 'other'])->default('phone');
            $table->enum('status', ['pending', 'sent', 'acknowledged', 'paid'])->default('pending');
            $table->integer('reminder_level')->default(1); // 1, 2, 3 for escalation
            $table->date('reminder_date');
            $table->date('next_reminder_date')->nullable();
            $table->text('message')->nullable();
            $table->text('response_note')->nullable();
            $table->decimal('amount_due', 15, 2);
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('acknowledged_by')->nullable()->constrained('users');
            $table->dateTime('acknowledged_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_reminders');
    }
};
