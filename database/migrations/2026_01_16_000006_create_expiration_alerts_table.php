<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expiration_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('product_lot_id')->constrained();
            $table->foreignId('warehouse_id')->constrained();

            $table->date('expiration_date');
            $table->integer('days_until_expiration');
            $table->double('quantity_at_risk', 64, 2);
            $table->enum('alert_level', ['warning', 'critical', 'expired'])->default('warning');
            $table->enum('status', ['active', 'acknowledged', 'resolved'])->default('active');

            $table->foreignId('acknowledged_by')->nullable()->constrained('users');
            $table->timestamp('acknowledged_at')->nullable();
            $table->text('action_taken')->nullable();

            $table->foreignId('company_id')->constrained();
            $table->timestamps();

            $table->index(['company_id', 'status', 'alert_level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expiration_alerts');
    }
};
