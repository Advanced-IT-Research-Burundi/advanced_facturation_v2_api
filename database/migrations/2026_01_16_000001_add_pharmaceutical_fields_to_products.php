<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Champs pharmaceutiques
            $table->boolean('is_pharmaceutical')->default(false)->after('type');
            $table->string('dci')->nullable()->after('is_pharmaceutical'); // Denomination Commune Internationale
            $table->string('dosage')->nullable()->after('dci'); // Ex: 500mg, 10mg/ml
            $table->string('forme_galenique')->nullable()->after('dosage'); // Comprimes, sirop, injectable
            $table->string('laboratoire')->nullable()->after('forme_galenique'); // Fabricant
            $table->string('numero_amm')->nullable()->after('laboratoire'); // Autorisation mise sur marche
            $table->boolean('requires_prescription')->default(false)->after('numero_amm');
            $table->string('classe_therapeutique')->nullable()->after('requires_prescription');
            $table->text('contre_indications')->nullable()->after('classe_therapeutique');
            $table->text('posologie_standard')->nullable()->after('contre_indications');
            $table->integer('delai_alerte_expiration')->default(90)->after('posologie_standard'); // Jours avant expiration
            $table->boolean('is_controlled_substance')->default(false)->after('delai_alerte_expiration'); // Stupefiants
            $table->string('storage_conditions')->nullable()->after('is_controlled_substance'); // Conditions de stockage
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'is_pharmaceutical',
                'dci',
                'dosage',
                'forme_galenique',
                'laboratoire',
                'numero_amm',
                'requires_prescription',
                'classe_therapeutique',
                'contre_indications',
                'posologie_standard',
                'delai_alerte_expiration',
                'is_controlled_substance',
                'storage_conditions'
            ]);
        });
    }
};
