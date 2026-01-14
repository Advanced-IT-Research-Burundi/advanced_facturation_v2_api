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
        Schema::disableForeignKeyConstraints();

        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // 1 pour le personne physique , 2 pour le personne morale ou societe etranger
            $table->enum('tp_type',[1,2])->default('1');
            $table->string('tp_name');
            $table->string('tp_TIN')->unique();
            $table->string('tp_trade_number')->nullable();
            $table->string('tp_postal_number')->nullable();
            $table->string('tp_phone_number')->nullable();
            $table->string('tp_address_province')->nullable();
            $table->string('tp_address_commune')->nullable();
            $table->string('tp_address_quartier')->nullable();
            $table->string('tp_address_avenue')->nullable();
            $table->string('tp_address_rue')->nullable();
            $table->string('tp_address_number')->nullable();
            $table->enum('tp_fiscal_center', [
            'DPMC',    
            'DMC',
            'DGC'
            ])->nullable();
            $table->string('tp_activity_sector')->nullable();
            $table->string('tp_legal_form')->nullable();
            $table->string('vat_taxpayer');
            $table->string('ct_taxpayer');
            $table->string('tl_taxpayer');
            $table->string('system_or_device_id')->nullable();
             $table->string('default_currency')->nullable();
            $table->string('obr_username')->nullable();
            $table->string('obr_password')->nullable();
            $table->string('web_site')->nullable();
            $table->string('tp_banque')->nullable();
            $table->string(column: 'tp_compte_name')->nullable();
            $table->string(column: 'company_logo')->nullable();
            $table->text(column: 'description')->nullable();
            $table->foreignId('user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
