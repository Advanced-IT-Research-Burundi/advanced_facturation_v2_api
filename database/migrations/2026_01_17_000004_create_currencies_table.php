<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 3)->unique(); // USD, EUR, FBU
            $table->string('name');
            $table->string('symbol', 10);
            $table->decimal('exchange_rate', 15, 6)->default(1); // Rate to base currency (FBU)
            $table->boolean('is_base')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('decimal_places')->default(2);
            $table->timestamps();
        });

        // Insert default currencies
        DB::table('currencies')->insert([
            [
                'code' => 'FBU',
                'name' => 'Franc Burundais',
                'symbol' => 'FBU',
                'exchange_rate' => 1,
                'is_base' => true,
                'is_active' => true,
                'decimal_places' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'USD',
                'name' => 'Dollar Américain',
                'symbol' => '$',
                'exchange_rate' => 2850,
                'is_base' => false,
                'is_active' => true,
                'decimal_places' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'EUR',
                'name' => 'Euro',
                'symbol' => '€',
                'exchange_rate' => 3100,
                'is_base' => false,
                'is_active' => true,
                'decimal_places' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // Exchange rate history
        Schema::create('exchange_rate_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('currency_id')->constrained()->onDelete('cascade');
            $table->decimal('rate', 15, 6);
            $table->date('effective_date');
            $table->foreignId('updated_by')->constrained('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exchange_rate_history');
        Schema::dropIfExists('currencies');
    }
};
