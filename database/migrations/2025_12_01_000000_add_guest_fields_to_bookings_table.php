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
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('guest_name')->nullable();
            $table->string('guest_surname')->nullable();
            $table->string('guest_second_name')->nullable();
            $table->date('guest_birthday')->nullable();
            $table->string('guest_phone')->nullable();
            $table->string('guest_email')->nullable();
            $table->string('guest_passport_series')->nullable();
            $table->string('guest_passport_number')->nullable();
            $table->date('guest_passport_issued_at')->nullable();
            $table->string('guest_passport_issued_by')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn([
                'guest_name',
                'guest_surname',
                'guest_second_name',
                'guest_birthday',
                'guest_phone',
                'guest_email',
                'guest_passport_series',
                'guest_passport_number',
                'guest_passport_issued_at',
                'guest_passport_issued_by',
            ]);
        });
    }
};
