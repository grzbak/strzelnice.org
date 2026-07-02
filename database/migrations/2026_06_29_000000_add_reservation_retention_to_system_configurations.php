<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_configurations', function (Blueprint $table) {
            $table->unsignedSmallInteger('reservation_operational_months')
                ->default(6)
                ->after('default_max_advance_booking_days');
            $table->unsignedSmallInteger('reservation_history_months')
                ->default(60)
                ->after('reservation_operational_months');
        });
    }

    public function down(): void
    {
        Schema::table('system_configurations', function (Blueprint $table) {
            $table->dropColumn([
                'reservation_operational_months',
                'reservation_history_months',
            ]);
        });
    }
};
