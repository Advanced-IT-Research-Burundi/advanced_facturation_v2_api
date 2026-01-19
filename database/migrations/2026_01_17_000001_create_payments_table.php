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
        if (!Schema::hasTable('payments')) {
            Schema::create('payments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('invoice_id')->constrained()->onDelete('cascade');
                $table->decimal('amount', 15, 2);
                $table->dateTime('payment_date');
                $table->enum('payment_method', ['cash', 'bank_transfer', 'mobile_money', 'check', 'card', 'other'])->default('cash');
                $table->string('reference')->nullable();
                $table->text('note')->nullable();
                $table->foreignId('created_by')->constrained('users');
                $table->foreignId('company_id')->constrained();
                $table->timestamps();
                $table->softDeletes();
            });
        } else {
            // Add missing columns to existing payments table
            Schema::table('payments', function (Blueprint $table) {
                if (!Schema::hasColumn('payments', 'company_id')) {
                    $table->foreignId('company_id')->nullable()->constrained();
                }
                if (!Schema::hasColumn('payments', 'deleted_at')) {
                    $table->softDeletes();
                }
            });
        }

        // Add payment tracking columns to invoices if they don't exist
        Schema::table('invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('invoices', 'payment_status')) {
                $table->enum('payment_status', ['unpaid', 'partial', 'paid'])->default('unpaid')->after('invoice_total_amount');
            }
            if (!Schema::hasColumn('invoices', 'total_paid')) {
                $table->decimal('total_paid', 15, 2)->default(0)->after('payment_status');
            }
            if (!Schema::hasColumn('invoices', 'due_date')) {
                $table->date('due_date')->nullable()->after('total_paid');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');

        Schema::table('invoices', function (Blueprint $table) {
            if (Schema::hasColumn('invoices', 'payment_status')) {
                $table->dropColumn('payment_status');
            }
            if (Schema::hasColumn('invoices', 'total_paid')) {
                $table->dropColumn('total_paid');
            }
            if (Schema::hasColumn('invoices', 'due_date')) {
                $table->dropColumn('due_date');
            }
        });
    }
};
