<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Table pour les tables physiques du restaurant
        Schema::create('restaurant_tables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->string('table_number', 20);
            $table->integer('capacity')->default(4);
            $table->enum('status', ['free', 'occupied', 'reserved'])->default('free');
            $table->string('location')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'table_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_tables');
    }
};
