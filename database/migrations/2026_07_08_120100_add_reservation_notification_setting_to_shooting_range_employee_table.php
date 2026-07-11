<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shooting_range_employee', function (Blueprint $table) {
            $table->boolean('receives_reservation_notifications')
                ->default(false)
                ->after('is_approved');
        });
    }

    public function down(): void
    {
        Schema::table('shooting_range_employee', function (Blueprint $table) {
            $table->dropColumn('receives_reservation_notifications');
        });
    }
};
