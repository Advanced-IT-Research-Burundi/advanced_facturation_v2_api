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
        Schema::create('mouvement_stock_importations', function (Blueprint $table) {
            $table->id();
            $table->foreignId("warehouse_id")->constrained();
            $table->foreignId("product_id")->constrained();
            $table->string("reference_dmc")->nullable();
            $table->string("rubrique_tarifaire")->nullable();
            $table->double("nombre_par_paquet" , 64,4)->nullable();
            $table->string("description_paquet")->nullable();
            $table->string("system_or_device_id");
            $table->string("item_code");
            $table->string("item_designation");
            $table->double("item_quantity", 64,4);
            $table->string("item_measurement_unit");
            $table->double("item_cost_price", 64,4);
            $table->string("item_cost_price_currency");
            $table->string("item_movement_type");
            $table->string("item_movement_invoice_ref")->nullable();
            $table->string("item_movement_description")->nullable();
            $table->string("item_product_name")->nullable();
            $table->boolean("is_sent_to_obr")->default(0);
            $table->string("obr_status")->nullable();
            $table->string("obr_message")->nullable();
            $table->dateTime("obr_sent_at")->nullable();
            $table->dateTime("item_movement_date");
            $table->timestamps();
            $table->softDeletes();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mouvement_stock_importations');
    }
};
