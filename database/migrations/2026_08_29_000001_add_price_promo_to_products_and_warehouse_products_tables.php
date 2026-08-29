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
        Schema::table('products', function (Blueprint $table) {
            $table->double('price_promo', 64, 2)->nullable()->default(0)->after('price');
        });

        Schema::table('warehouse_products', function (Blueprint $table) {
            $table->double('price_promo', 64, 4)->nullable()->default(0)->after('unit_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('warehouse_products', function (Blueprint $table) {
            $table->dropColumn('price_promo');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('price_promo');
        });
    }
};
