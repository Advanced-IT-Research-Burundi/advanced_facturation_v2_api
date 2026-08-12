<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_pos_carts', function (Blueprint $table) {
            $table->id();
            $table->string('local_id')->index();
            $table->string('identifier')->index();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->string('currency', 3)->default('BIF');
            $table->string('payment_type', 50)->default('1');
            $table->decimal('total_ht', 15, 2)->default(0);
            $table->decimal('total_tva', 15, 2)->default(0);
            $table->decimal('total_ttc', 15, 2)->default(0);
            $table->json('customer_snapshot')->nullable();
            $table->json('items');
            $table->timestamps();

            $table->unique(['company_id', 'local_id']);
            $table->unique(['company_id', 'identifier']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_pos_carts');
    }
};
