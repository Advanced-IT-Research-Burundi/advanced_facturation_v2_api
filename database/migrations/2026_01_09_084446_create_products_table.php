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

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('item_code')->unique();
            $table->string('item_designation');
            $table->string('item_measurement_unit');
            $table->date('date_expiration')->nullable();
            $table->string('barcode')->nullable();
            $table->double('vat_rate')->default(0);
            $table->foreignId('product_unit_id')->nullable();
            $table->foreignId('product_category_id')->nullable();

            $table->string('code_product')->nullable();
            $table->string('marque')->nullable();
            $table->double('quantite', 64,2)->default(0)->nullable();
            $table->double('quantite_alert', 64,2)->default(0)->nullable();
            $table->double('price', 64,2)->default(0)->nullable();
            $table->double('price_ttc', 64,2)->default(0)->nullable();
            $table->double('price_max', 64,2)->default(0)->nullable();
            $table->double('price_min', 64,2)->default(0)->nullable();
            $table->double('price_tvac', 64,2)->default(0)->nullable();
            $table->double('item_ott_tax', 64,2)->default(0)->nullable();
            $table->double('item_tsce_tax', 64,2)->default(0)->nullable();
            $table->date('date_expiration')->nullable();
            $table->string('image')->nullable();
            $table->string('type')->nullable();
            $table->foreignId('company_id')->nullable();
            $table->foreignId('user_id')->constrained();
            $table->text('description')->nullable();
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
        Schema::dropIfExists('products');
    }
};
