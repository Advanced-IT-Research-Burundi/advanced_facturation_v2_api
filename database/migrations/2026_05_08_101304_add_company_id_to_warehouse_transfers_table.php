<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('warehouse_transfers', function (Blueprint $table) {
            // $table->foreignId('company_id')
            //     ->nullable()
            //     ->after('id')
            //     ->constrained('companies')
            //     ->onDelete('cascade');
        });

        DB::table('warehouse_transfers')
            ->whereNull('company_id')
            ->update([
                'company_id' => DB::raw('(SELECT w.company_id FROM warehouses w WHERE w.id = warehouse_transfers.source_warehouse_id LIMIT 1)'),
            ]);
    }

    public function down(): void
    {
        Schema::table('warehouse_transfers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('company_id');
        });
    }
};
