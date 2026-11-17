<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouse_transfer_items', function (Blueprint $table) {
            $table->double('purchase_price', 64, 4)->nullable()->default(0)->after('unit_price');
        });
    }

    public function down(): void
    {
        Schema::table('warehouse_transfer_items', function (Blueprint $table) {
            $table->dropColumn('purchase_price');
        });
    }
};
