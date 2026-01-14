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
        Schema::disableForeignKeyConstraints();

        Schema::create('warehouse_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('warehouse_id')->nullable()->constrained();
            $table->double('quantity')->nullable();
            $table->double('unit_price')->nullable();
            $table->string('currency')->nullable();
            $table->foreignId('last_stock_movement_id')->constrained('stock_movements');
            $table->foreignId('user_id')->constrained();
            $table->foreignId('last_stock_movement_id_id');
            $table->unique(['product_id', 'warehouse_id']);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('warehouse_products');
    }
};
