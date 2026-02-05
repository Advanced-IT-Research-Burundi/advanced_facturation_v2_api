<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('restaurant_table_id')->nullable()->after('customer_id')
                ->constrained('restaurant_tables')->onDelete('set null');
            $table->foreignId('server_id')->nullable()->after('restaurant_table_id')
                ->constrained('users')->onDelete('set null');
            $table->boolean('is_restaurant')->default(false)->after('server_id');
            $table->json('restaurant_order_ids')->nullable()->after('is_restaurant');
        });

        // Ajouter is_server aux utilisateurs
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_server')->default(false)->after('email');
            $table->string('server_code', 10)->nullable()->after('is_server');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['restaurant_table_id']);
            $table->dropForeign(['server_id']);
            $table->dropColumn(['restaurant_table_id', 'server_id', 'is_restaurant', 'restaurant_order_ids']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_server', 'server_code']);
        });
    }
};
