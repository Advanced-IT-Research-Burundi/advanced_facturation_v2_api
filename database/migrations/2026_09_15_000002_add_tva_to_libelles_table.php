<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('libelles', function (Blueprint $table) {
            $table->decimal('tva', 5, 2)->nullable()->after('price');
        });
    }

    public function down(): void
    {
        Schema::table('libelles', function (Blueprint $table) {
            $table->dropColumn('tva');
        });
    }
};
