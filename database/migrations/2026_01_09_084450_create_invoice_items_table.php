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

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained();
            $table->string('item_designation');
            $table->double('item_quantity');
            $table->double('item_price');
            $table->double('item_ct')->nullable();
            $table->double('item_tl')->nullable();
            $table->double('item_ott_tax')->nullable();
            $table->double('item_tsce_tax')->nullable();
            $table->double('item_price_nvat');
            $table->double('vat');
            $table->double('item_price_wvat');
            $table->double('item_total_amount');
            $table->foreignId('user_id')->constrained();
            $table->softDeletes();
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
