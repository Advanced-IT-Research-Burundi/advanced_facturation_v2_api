<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotel_kitchen_stocks', function (Blueprint $table) {
            $table->decimal('purchase_price', 15, 2)->default(0)->after('alert_threshold');
        });

        Schema::table('hotel_bar_stocks', function (Blueprint $table) {
            $table->decimal('purchase_price', 15, 2)->default(0)->after('alert_threshold');
        });
    }

    public function down(): void
    {
        Schema::table('hotel_kitchen_stocks', function (Blueprint $table) {
            $table->dropColumn('purchase_price');
        });

        Schema::table('hotel_bar_stocks', function (Blueprint $table) {
            $table->dropColumn('purchase_price');
        });
    }
};
