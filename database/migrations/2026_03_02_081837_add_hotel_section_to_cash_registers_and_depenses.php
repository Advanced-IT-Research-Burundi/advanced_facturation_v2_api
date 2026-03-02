<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_registers', function (Blueprint $table) {
            $table->string('hotel_section')->nullable()->after('warehouse_id')
                ->comment('restaurant, bar, rooms, conference, reception');
        });

        Schema::table('depenses', function (Blueprint $table) {
            $table->string('hotel_section')->nullable()->after('user_id')
                ->comment('restaurant, bar, rooms, conference, reception');
        });
    }

    public function down(): void
    {
        Schema::table('cash_registers', function (Blueprint $table) {
            $table->dropColumn('hotel_section');
        });

        Schema::table('depenses', function (Blueprint $table) {
            $table->dropColumn('hotel_section');
        });
    }
};
