<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lanes', function (Blueprint $table) {
            $table->boolean('is_archived')
                ->default(false)
                ->after('allows_dynamic_shooting');
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->boolean('is_dynamic_shooting')
                ->default(false)
                ->after('positions_count');
        });

        $hasOrphanRanges = DB::table('reservations')
            ->leftJoin(
                'shooting_ranges',
                'shooting_ranges.id',
                '=',
                'reservations.shooting_range_id'
            )
            ->whereNull('shooting_ranges.id')
            ->exists();
        $hasOrphanLanes = DB::table('reservations')
            ->leftJoin('lanes', 'lanes.id', '=', 'reservations.line_id')
            ->whereNull('lanes.id')
            ->exists();

        if ($hasOrphanRanges || $hasOrphanLanes) {
            throw new \RuntimeException(
                'Nie można zabezpieczyć rezerwacji kluczami obcymi: istnieją osierocone rekordy.'
            );
        }

        Schema::table('reservations', function (Blueprint $table) {
            $table->foreign('shooting_range_id')
                ->references('id')
                ->on('shooting_ranges')
                ->restrictOnDelete();
            $table->foreign('line_id')
                ->references('id')
                ->on('lanes')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropForeign(['shooting_range_id']);
            $table->dropForeign(['line_id']);
            $table->dropColumn('is_dynamic_shooting');
        });

        Schema::table('lanes', function (Blueprint $table) {
            $table->dropColumn('is_archived');
        });
    }
};
