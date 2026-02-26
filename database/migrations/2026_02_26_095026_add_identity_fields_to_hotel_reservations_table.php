<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotel_reservations', function (Blueprint $table) {
            $table->string('guest_id_number')->nullable()->after('guest_email');
            $table->string('guest_id_type')->default('cni')->after('guest_id_number'); // cni, passport
            $table->string('guest_address')->nullable()->after('guest_id_type');
            $table->string('guest_birthplace')->nullable()->after('guest_address');
            $table->date('guest_birthdate')->nullable()->after('guest_birthplace');
        });
    }

    public function down(): void
    {
        Schema::table('hotel_reservations', function (Blueprint $table) {
            $table->dropColumn([
                'guest_id_number',
                'guest_id_type',
                'guest_address',
                'guest_birthplace',
                'guest_birthdate',
            ]);
        });
    }
};
