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
        Schema::table('depenses', function (Blueprint $table) {
            $table->string('payment_method')->default('cash')->after('montant');
            $table->string('currency_code', 3)->default('BIF')->after('payment_method');
            $table->decimal('exchange_rate', 15, 6)->default(1)->after('currency_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('depenses', function (Blueprint $table) {
            $table->dropColumn(['payment_method', 'currency_code', 'exchange_rate']);
        });
    }
};
