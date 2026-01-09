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

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->string('system_or_device_id');
            $table->string('item_code');
            $table->string('item_designation');
            $table->double('item_quantity');
            $table->string('item_measurement_unit');
            $table->double('item_purchase_or_sale_price');
            $table->string('item_purchase_or_sale_currency');
            $table->string('item_movement_type');
            $table->string('item_movement_invoice_ref')->nullable();
            $table->string('item_movement_description')->nullable();
            $table->dateTime('item_movement_date');
            $table->enum('obr_submission_status', ["PENDING","SENT","ACCEPTED","REJECTED"]);
            $table->text('obr_response_message')->nullable();
            $table->dateTime('obr_sent_at')->nullable();
            $table->foreignId('company_id')->constrained();
            $table->foreignId('product_id')->constrained('');
            $table->foreignId('warehouse_id')->constrained('');
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
        Schema::dropIfExists('stock_movements');
    }
};
