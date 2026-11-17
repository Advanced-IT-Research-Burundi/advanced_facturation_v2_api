<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            // Prix d'achat réel au moment de la vente (instantané), utilisé pour le bénéfice.
            $table->double('purchase_price', 64, 4)->nullable()->default(0)->after('product_id');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropColumn('purchase_price');
        });
    }
};
