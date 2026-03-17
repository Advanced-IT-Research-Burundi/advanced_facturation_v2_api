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
        Schema::table('hotel_stock_movements', function (Blueprint $table) {
            $table->decimal('unit_price', 15, 2)->nullable()->after('quantity_after');
            $table->string('currency', 10)->default('BIF')->after('unit_price');
        });
    }

    public function down(): void
    {
        Schema::table('hotel_stock_movements', function (Blueprint $table) {
            $table->dropColumn(['unit_price', 'currency']);
        });
    }
};
