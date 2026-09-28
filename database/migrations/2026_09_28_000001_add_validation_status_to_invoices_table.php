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
            $table->string('status', 30)->default('not_validated')->after('invoice_total_amount');
            $table->boolean('is_validated')->default(false)->after('status');
            $table->dateTime('validated_at')->nullable()->after('is_validated');
            $table->foreignId('validated_by')->nullable()->after('validated_at')->constrained('users', 'id')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['validated_by']);
            $table->dropColumn(['status', 'is_validated', 'validated_at', 'validated_by']);
        });
    }
};
