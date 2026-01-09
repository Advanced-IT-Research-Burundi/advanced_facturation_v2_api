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
        Schema::disableForeignKeyConstraints();

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number');
            $table->dateTime('invoice_date');
            $table->string('invoice_type');
            $table->string('invoice_identifier');
            $table->string('invoice_currency');
            $table->string('tp_type');
            $table->string('tp_name');
            $table->string('tp_TIN');
            $table->string('tp_trade_number')->nullable();
            $table->string('tp_phone_number')->nullable();
            $table->string('tp_fiscal_center')->nullable();
            $table->string('vat_taxpayer');
            $table->string('ct_taxpayer');
            $table->string('tl_taxpayer');
            $table->string('customer_name');
            $table->string('customer_TIN')->nullable();
            $table->string('customer_address')->nullable();
            $table->string('vat_customer_payer');
            $table->decimal('invoice_amount_nvat', 15, 2);
            $table->decimal('invoice_vat_amount', 15, 2);
            $table->decimal('invoice_total_amount', 15, 2);
            $table->string('invoice_registered_number')->nullable();
            $table->dateTime('invoice_registered_date')->nullable();
            $table->text('electronic_signature')->nullable();
            $table->enum('obr_submission_status', ["PENDING","SENT","ACCEPTED","REJECTED"]);
            $table->text('obr_response_message')->nullable();
            $table->foreignId('company_id')->constrained();
            $table->foreignId('customer_id')->constrained('');
            $table->foreignId('created_by')->constrained('users', 'by');
            $table->foreignId('user_id')->constrained();
            $table->foreignId('created_by_id');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
