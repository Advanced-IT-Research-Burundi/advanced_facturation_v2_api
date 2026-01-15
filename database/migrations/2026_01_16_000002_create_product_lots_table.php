<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_lots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('warehouse_id')->constrained()->onDelete('cascade');
            $table->string('lot_number');
            $table->date('manufacturing_date')->nullable();
            $table->date('expiration_date');
            $table->double('initial_quantity', 64, 2)->default(0);
            $table->double('current_quantity', 64, 2)->default(0);
            $table->double('purchase_price', 64, 2)->nullable();
            $table->string('supplier_reference')->nullable();
            $table->foreignId('fournisseur_id')->nullable()->constrained('fourinsseurs');
            $table->enum('status', ['active', 'expired', 'recalled', 'depleted'])->default('active');
            $table->text('notes')->nullable();
            $table->foreignId('company_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->timestamps();
            $table->softDeletes();

            // Index pour recherche rapide
            $table->index(['product_id', 'warehouse_id', 'status']);
            $table->index(['expiration_date', 'status']);
            $table->unique(['product_id', 'warehouse_id', 'lot_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_lots');
    }
};
