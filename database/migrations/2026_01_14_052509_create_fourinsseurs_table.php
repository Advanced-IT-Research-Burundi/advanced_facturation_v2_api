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
        Schema::create('fourinsseurs', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('phone_number')->nullable();
            $table->text('nif')->nullable();
            $table->text('email')->nullable();
            $table->text('address')->nullable();
            $table->foreignId('company_id')->nullable();
            $table->foreignId('user_id');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fourinsseurs');
    }
};
