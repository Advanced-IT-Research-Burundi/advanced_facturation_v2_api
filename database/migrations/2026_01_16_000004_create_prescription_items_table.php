<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prescription_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prescription_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained();
            $table->foreignId('product_lot_id')->nullable()->constrained();

            $table->double('prescribed_quantity', 64, 2);
            $table->double('dispensed_quantity', 64, 2)->default(0);
            $table->string('dosage_instructions')->nullable();
            $table->integer('treatment_duration')->nullable();
            $table->text('notes')->nullable();

            $table->enum('status', ['pending', 'partially_dispensed', 'fully_dispensed', 'cancelled'])
                  ->default('pending');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prescription_items');
    }
};
