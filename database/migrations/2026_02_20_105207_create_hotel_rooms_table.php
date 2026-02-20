<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotel_rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->string('room_number', 20);
            $table->string('name')->nullable();
            $table->enum('type', ['standard', 'double', 'suite', 'vip'])->default('standard');
            $table->string('floor', 10)->nullable();
            $table->integer('capacity')->default(1);
            $table->decimal('price_per_night', 15, 2)->default(0);
            $table->enum('status', ['available', 'occupied', 'reserved', 'maintenance'])->default('available');
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'room_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_rooms');
    }
};
