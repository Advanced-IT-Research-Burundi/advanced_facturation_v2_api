<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotel_restaurant_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_restaurant_order_id')->constrained()->onDelete('cascade');
            $table->foreignId('hotel_menu_item_id')->nullable()->constrained()->onDelete('set null');
            $table->string('name');
            $table->decimal('price', 15, 2)->default(0);
            $table->integer('qty')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_restaurant_order_items');
    }
};
