<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotel_stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->enum('stock_type', ['bar', 'kitchen']);
            $table->unsignedBigInteger('stock_item_id');
            $table->string('stock_item_name');
            $table->enum('movement_type', ['in', 'out']);
            $table->decimal('quantity', 10, 3);
            $table->decimal('quantity_before', 10, 3)->default(0);
            $table->decimal('quantity_after', 10, 3)->default(0);
            $table->string('reason')->nullable();
            $table->string('reference')->nullable();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_stock_movements');
    }
};
