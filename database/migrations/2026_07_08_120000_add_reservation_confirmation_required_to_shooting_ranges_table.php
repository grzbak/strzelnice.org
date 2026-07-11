<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shooting_ranges', function (Blueprint $table) {
            $table->boolean('requires_reservation_confirmation')
                ->default(true)
                ->after('max_advance_booking_days');
        });
    }

    public function down(): void
    {
        Schema::table('shooting_ranges', function (Blueprint $table) {
            $table->dropColumn('requires_reservation_confirmation');
        });
    }
};
