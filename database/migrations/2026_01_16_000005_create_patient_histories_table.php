<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained();
            $table->foreignId('product_lot_id')->nullable()->constrained();
            $table->foreignId('invoice_id')->nullable()->constrained();
            $table->foreignId('prescription_id')->nullable()->constrained();

            $table->double('quantity', 64, 2);
            $table->double('unit_price', 64, 2);
            $table->date('purchase_date');
            $table->string('lot_number')->nullable();
            $table->date('lot_expiration')->nullable();

            // Pour les alertes et rappels
            $table->text('notes')->nullable();
            $table->boolean('requires_followup')->default(false);
            $table->date('followup_date')->nullable();

            $table->foreignId('company_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['customer_id', 'product_id']);
            $table->index(['customer_id', 'purchase_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_histories');
    }
};
