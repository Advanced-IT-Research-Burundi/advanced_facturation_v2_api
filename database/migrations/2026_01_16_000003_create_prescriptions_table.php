<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prescriptions', function (Blueprint $table) {
            $table->id();
            $table->string('prescription_number')->unique();
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->foreignId('invoice_id')->nullable()->constrained()->onDelete('set null');

            // Informations patient
            $table->string('patient_name');
            $table->date('patient_birthdate')->nullable();
            $table->string('patient_phone')->nullable();

            // Informations medecin
            $table->string('prescriber_name');
            $table->string('prescriber_registration')->nullable();
            $table->string('prescriber_phone')->nullable();
            $table->string('prescriber_address')->nullable();

            // Details ordonnance
            $table->date('prescription_date');
            $table->date('validity_date')->nullable();
            $table->text('diagnosis')->nullable();
            $table->text('notes')->nullable();

            // Statut
            $table->enum('status', ['pending', 'partially_dispensed', 'fully_dispensed', 'expired', 'cancelled'])
                  ->default('pending');

            // Image de l'ordonnance scannee
            $table->string('prescription_image')->nullable();

            $table->foreignId('company_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('dispensed_by')->nullable()->constrained('users');
            $table->timestamp('dispensed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prescriptions');
    }
};
