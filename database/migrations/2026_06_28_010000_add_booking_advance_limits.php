<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_configurations', function (Blueprint $table) {
            $table->unsignedSmallInteger('default_max_advance_booking_days')
                ->default(30)
                ->after('bank_account_number');
        });

        Schema::table('shooting_ranges', function (Blueprint $table) {
            $table->unsignedSmallInteger('max_advance_booking_days')
                ->nullable()
                ->after('is_public_access');
        });
    }

    public function down(): void
    {
        Schema::table('shooting_ranges', function (Blueprint $table) {
            $table->dropColumn('max_advance_booking_days');
        });

        Schema::table('system_configurations', function (Blueprint $table) {
            $table->dropColumn('default_max_advance_booking_days');
        });
    }
};
