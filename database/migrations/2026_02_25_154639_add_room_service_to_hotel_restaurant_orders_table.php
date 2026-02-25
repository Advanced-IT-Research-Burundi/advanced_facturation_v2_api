<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotel_restaurant_orders', function (Blueprint $table) {
            $table->foreignId('hotel_restaurant_table_id')
                ->nullable()
                ->change();

            $table->string('room_number')->nullable()->after('hotel_restaurant_table_id');
            $table->boolean('is_room_service')->default(false)->after('room_number');
        });
    }

    public function down(): void
    {
        Schema::table('hotel_restaurant_orders', function (Blueprint $table) {
            $table->dropColumn(['room_number', 'is_room_service']);
            $table->foreignId('hotel_restaurant_table_id')
                ->nullable(false)
                ->change();
        });
    }
};
