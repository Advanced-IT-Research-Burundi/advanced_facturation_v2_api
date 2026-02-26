<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotel_conference_bookings', function (Blueprint $table) {
            $table->foreignId('invoice_id')
                ->nullable()
                ->after('status')
                ->constrained('invoices')
                ->nullOnDelete();
            $table->decimal('total_amount', 15, 2)->default(0)->after('advance_payment');
        });
    }

    public function down(): void
    {
        Schema::table('hotel_conference_bookings', function (Blueprint $table) {
            $table->dropForeign(['invoice_id']);
            $table->dropColumn(['invoice_id', 'total_amount']);
        });
    }
};
