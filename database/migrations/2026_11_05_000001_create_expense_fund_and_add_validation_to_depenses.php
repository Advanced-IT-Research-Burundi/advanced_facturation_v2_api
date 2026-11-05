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
        // Les dépenses existantes ont déjà été retirées d'une caisse : elles restent validées
        Schema::table('depenses', function (Blueprint $table) {
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('approved')->after('hotel_section');
            $table->foreignId('validated_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable()->after('validated_by');
            $table->string('rejection_reason')->nullable()->after('validated_at');
        });

        // Caisse des dépenses : une par entreprise, sans ouverture ni clôture
        Schema::create('expense_fund_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('depense_id')->nullable()->constrained('depenses')->nullOnDelete();
            $table->enum('type', ['deposit', 'expense']);
            $table->decimal('amount', 15, 2);
            $table->string('description');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expense_fund_movements');

        Schema::table('depenses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('validated_by');
            $table->dropColumn(['status', 'validated_at', 'rejection_reason']);
        });
    }
};
