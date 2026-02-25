<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotel_dishes', function (Blueprint $table) {
            $table->foreignId('kitchen_stock_id')
                ->nullable()
                ->after('available')
                ->constrained('hotel_kitchen_stocks')
                ->nullOnDelete();
            $table->decimal('stock_per_serving', 10, 3)
                ->default(1)
                ->after('kitchen_stock_id')
                ->comment('Quantité de stock consommée par portion commandée');
        });
    }

    public function down(): void
    {
        Schema::table('hotel_dishes', function (Blueprint $table) {
            $table->dropForeign(['kitchen_stock_id']);
            $table->dropColumn(['kitchen_stock_id', 'stock_per_serving']);
        });
    }
};
