<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotel_restaurant_tables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->string('number', 20);
            $table->integer('seats')->default(4);
            $table->string('location')->nullable();
            $table->enum('status', ['free', 'occupied'])->default('free');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_restaurant_tables');
    }
};
