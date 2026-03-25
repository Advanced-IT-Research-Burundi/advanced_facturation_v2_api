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
        // Disable foreign key constraints temporarily
        Schema::disableForeignKeyConstraints();

        // Try to remove the old constraint if it exists
        try {
            DB::statement('ALTER TABLE warehouse_products DROP INDEX warehouse_products_product_id_warehouse_id_unique');
        } catch (\Exception $e) {
            // Index might not exist, continue
        }

        // Add the new unique constraint that includes production_status
        Schema::table('warehouse_products', function (Blueprint $table) {
            $table->unique(['product_id', 'warehouse_id', 'production_status'], 'wh_prod_unique')->nullable();
        });

        // Re-enable foreign key constraints
        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();

        // Try to remove the new constraint if it exists
        try {
            DB::statement('ALTER TABLE warehouse_products DROP INDEX wh_prod_unique');
        } catch (\Exception $e) {
            // Index might not exist, continue
        }

        // Restore the old constraint
        Schema::table('warehouse_products', function (Blueprint $table) {
            $table->unique(['product_id', 'warehouse_id'])->nullable();
        });

        Schema::enableForeignKeyConstraints();
    }
};
