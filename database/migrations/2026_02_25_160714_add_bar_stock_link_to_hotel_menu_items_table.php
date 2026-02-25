<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotel_menu_items', function (Blueprint $table) {
            $table->foreignId('bar_stock_id')
                ->nullable()
                ->after('available')
                ->constrained('hotel_bar_stocks')
                ->nullOnDelete();
            $table->decimal('stock_per_serving', 10, 3)
                ->default(1)
                ->after('bar_stock_id')
                ->comment('Quantité de stock consommée par unité commandée');
        });
    }

    public function down(): void
    {
        Schema::table('hotel_menu_items', function (Blueprint $table) {
            $table->dropForeign(['bar_stock_id']);
            $table->dropColumn(['bar_stock_id', 'stock_per_serving']);
        });
    }
};
