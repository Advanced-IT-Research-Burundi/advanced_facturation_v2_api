<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->enum('domain', [
                'general',
                'hotel',
                'pharmaceutical',
                'restaurant',
                'bakery',
            ])->nullable()->after('description')
                ->comment('null = universel (tous domaines), sinon restreint au domaine spécifié');
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('domain');
        });
    }
};
