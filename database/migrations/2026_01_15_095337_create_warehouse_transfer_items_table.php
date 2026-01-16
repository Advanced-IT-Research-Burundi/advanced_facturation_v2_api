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
        Schema::create('warehouse_transfer_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transfer_id')->constrained('warehouse_transfers')->onDelete('cascade');
            $table->foreignId('product_id')->constrained();
            $table->double('quantity');
            $table->double('unit_price');
            $table->string('currency');
            $table->foreignId('stock_movement_out_id')->nullable()->constrained('stock_movements');
            $table->foreignId('stock_movement_in_id')->nullable()->constrained('stock_movements');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('warehouse_transfer_items');
    }
};
