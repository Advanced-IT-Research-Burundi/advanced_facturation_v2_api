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
        Schema::create('obr_logs', function (Blueprint $table) {
            $table->id();
            
            // Type de log (INVOICE, STOCK_MOVEMENT, CANCEL)
            $table->string('log_type', 50);
            
            // Référence vers la facture ou le mouvement
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->unsignedBigInteger('stock_movement_id')->nullable();
            
            // Identifiants OBR
            $table->string('invoice_identifier')->nullable();
            $table->string('invoice_number')->nullable();
            
            // Statut de la requête
            $table->boolean('success')->default(false);
            $table->string('status', 50)->default('PENDING'); // PENDING, ACCEPTED, REJECTED
            
            // Réponse OBR
            $table->string('obr_message')->nullable();
            $table->text('obr_response')->nullable();
            $table->string('electronic_signature')->nullable();
            $table->string('invoice_registered_number')->nullable();
            $table->timestamp('invoice_registered_date')->nullable();
            
            // Corps de la requête (pour debug)
            $table->json('request_body')->nullable();
            
            // Tentatives
            $table->integer('retry_count')->default(0);
            $table->timestamp('last_retry_at')->nullable();
            // Utilisateur
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
            // Index
            $table->index(['log_type', 'status']);
            $table->index('invoice_id');
            $table->index('invoice_identifier');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('obr_logs');
    }
};
