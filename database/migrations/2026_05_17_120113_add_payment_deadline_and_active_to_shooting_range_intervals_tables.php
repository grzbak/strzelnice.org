<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shooting_range_interval', function (Blueprint $table) {
            $table->date('payment_due_date')
                ->nullable()
                ->after('datePaid');

            $table->boolean('is_active')
                ->default(true)
                ->after('payment_due_date');
        });

        Schema::table('shooting_range_interval_archive', function (Blueprint $table) {
            $table->date('payment_due_date')
                ->nullable()
                ->after('datePaid');

            $table->boolean('is_active')
                ->default(false)
                ->after('payment_due_date');
        });
    }

    public function down(): void
    {
        Schema::table('shooting_range_interval', function (Blueprint $table) {
            $table->dropColumn(['payment_due_date', 'is_active']);
        });

        Schema::table('shooting_range_interval_archive', function (Blueprint $table) {
            $table->dropColumn(['payment_due_date', 'is_active']);
        });
    }
};
