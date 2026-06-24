<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->backfillExistingShootingRanges();
        $this->createDefaultOpeningHoursTrigger();
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS shooting_ranges_after_insert_default_opening_hours');
    }

    private function backfillExistingShootingRanges(): void
    {
        $shootingRangeIds = DB::table('shooting_ranges')
            ->leftJoin(
                'shooting_range_opening_hours',
                'shooting_ranges.id',
                '=',
                'shooting_range_opening_hours.shooting_range_id'
            )
            ->select('shooting_ranges.id')
            ->groupBy('shooting_ranges.id')
            ->havingRaw('COUNT(shooting_range_opening_hours.id) = 0')
            ->pluck('shooting_ranges.id');

        foreach ($shootingRangeIds->chunk(200) as $chunk) {
            $rows = [];
            $now = now();

            foreach ($chunk as $shootingRangeId) {
                foreach (range(1, 7) as $dayOfWeek) {
                    $rows[] = [
                        'shooting_range_id' => $shootingRangeId,
                        'day_of_week' => $dayOfWeek,
                        'opens_at' => '00:00:00',
                        'closes_at' => '23:59:59',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            DB::table('shooting_range_opening_hours')->insert($rows);
        }
    }

    private function createDefaultOpeningHoursTrigger(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS shooting_ranges_after_insert_default_opening_hours');

        DB::unprepared("
            CREATE TRIGGER shooting_ranges_after_insert_default_opening_hours
            AFTER INSERT ON shooting_ranges
            FOR EACH ROW
            BEGIN
                INSERT INTO shooting_range_opening_hours
                    (shooting_range_id, day_of_week, opens_at, closes_at, created_at, updated_at)
                VALUES
                    (NEW.id, 1, '00:00:00', '23:59:59', NOW(), NOW()),
                    (NEW.id, 2, '00:00:00', '23:59:59', NOW(), NOW()),
                    (NEW.id, 3, '00:00:00', '23:59:59', NOW(), NOW()),
                    (NEW.id, 4, '00:00:00', '23:59:59', NOW(), NOW()),
                    (NEW.id, 5, '00:00:00', '23:59:59', NOW(), NOW()),
                    (NEW.id, 6, '00:00:00', '23:59:59', NOW(), NOW()),
                    (NEW.id, 7, '00:00:00', '23:59:59', NOW(), NOW());
            END
        ");
    }
};