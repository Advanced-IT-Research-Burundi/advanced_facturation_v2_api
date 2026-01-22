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
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->string('log_type')->index(); // invoice, product, stock, customer, payment, auth, etc.
            $table->string('action'); // created, updated, deleted, viewed, login, logout, etc.
            $table->string('description');
            $table->string('subject_type')->nullable(); // App\Models\Invoice, App\Models\Product, etc.
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('properties')->nullable(); // Additional data (old values, new values, etc.)
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();

            $table->index(['subject_type', 'subject_id']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
