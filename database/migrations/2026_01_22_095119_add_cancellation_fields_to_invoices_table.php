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
            $table->boolean('is_cancelled')->default(false)->after('obr_submission_status');
            $table->timestamp('cancelled_at')->nullable()->after('is_cancelled');
            $table->unsignedBigInteger('cancelled_by')->nullable()->after('cancelled_at');
            $table->text('cancel_reason')->nullable()->after('cancelled_by');
            
            // Champs OBR additionnels
            $table->string('obr_invoice_identifier')->nullable()->after('obr_submission_status');
            $table->string('obr_invoice_registered_number')->nullable()->after('obr_invoice_identifier');
            $table->timestamp('obr_invoice_registered_date')->nullable()->after('obr_invoice_registered_number');
            $table->text('obr_electronic_signature')->nullable()->after('obr_invoice_registered_date');
            $table->timestamp('obr_sent_at')->nullable()->after('obr_electronic_signature');
            
            // Index
            $table->index('is_cancelled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn([
                'is_cancelled',
                'cancelled_at',
                'cancelled_by',
                'cancel_reason',
                'obr_invoice_identifier',
                'obr_invoice_registered_number',
                'obr_invoice_registered_date',
                'obr_electronic_signature',
                'obr_sent_at',
            ]);
        });
    }
};
