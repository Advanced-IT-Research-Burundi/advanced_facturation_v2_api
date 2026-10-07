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
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('cancelled_invoice_ref', 30)->nullable()->after('obr_invoice_identifier')->index();
            $table->string('old_invoice_reference', 50)->nullable()->after('cancelled_invoice_ref')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['cancelled_invoice_ref']);
            $table->dropIndex(['old_invoice_reference']);
            $table->dropColumn(['cancelled_invoice_ref', 'old_invoice_reference']);
        });
    }
};
