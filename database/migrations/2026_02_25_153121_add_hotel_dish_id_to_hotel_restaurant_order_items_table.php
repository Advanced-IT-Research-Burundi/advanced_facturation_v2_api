<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotel_restaurant_order_items', function (Blueprint $table) {
            $table->foreignId('hotel_dish_id')
                ->nullable()
                ->after('hotel_menu_item_id')
                ->constrained('hotel_dishes')
                ->nullOnDelete();
            $table->string('item_type')->default('menu')->after('hotel_dish_id')
                ->comment('menu = article bar, dish = plat cuisine');
        });
    }

    public function down(): void
    {
        Schema::table('hotel_restaurant_order_items', function (Blueprint $table) {
            $table->dropForeign(['hotel_dish_id']);
            $table->dropColumn(['hotel_dish_id', 'item_type']);
        });
    }
};
