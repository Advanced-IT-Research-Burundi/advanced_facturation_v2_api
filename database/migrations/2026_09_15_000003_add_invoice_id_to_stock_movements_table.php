<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('stock_movements', 'invoice_id')) {
            Schema::table('stock_movements', function (Blueprint $table) {
                $table->foreignId('invoice_id')
                    ->nullable()
                    ->after('warehouse_id')
                    ->constrained()
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('warehouses', 'parent_id')) {
            Schema::table('warehouses', function (Blueprint $table) {
                $table->foreignId('parent_id')
                    ->nullable()
                    ->after('company_id')
                    ->constrained('warehouses')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('stock_movements', 'invoice_id')) {
            Schema::table('stock_movements', function (Blueprint $table) {
                $table->dropForeign(['invoice_id']);
                $table->dropColumn('invoice_id');
            });
        }

        if (Schema::hasColumn('warehouses', 'parent_id')) {
            Schema::table('warehouses', function (Blueprint $table) {
                $table->dropForeign(['parent_id']);
                $table->dropColumn('parent_id');
            });
        }
    }
};
