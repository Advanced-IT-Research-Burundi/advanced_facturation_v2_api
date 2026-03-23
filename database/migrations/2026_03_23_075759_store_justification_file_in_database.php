<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('depenses', function (Blueprint $table) {
            $table->longText('justification_data')->nullable()->after('justification_file');
            $table->string('justification_mime', 100)->nullable()->after('justification_data');
        });
    }

    public function down(): void
    {
        Schema::table('depenses', function (Blueprint $table) {
            $table->dropColumn(['justification_data', 'justification_mime']);
        });
    }
};
