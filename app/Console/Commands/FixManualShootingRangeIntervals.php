<?php

namespace App\Console\Commands;

use App\Models\ShootingRangeInterval;
use App\Models\ShootingRangeIntervalArchive;
use App\Models\ShootingRangeIntervalEvent;
use Illuminate\Console\Command;

class FixManualShootingRangeIntervals extends Command
{
    protected $signature = 'shooting-range:fix-manual-intervals {--dry-run}';

    protected $description = 'Marks active manual shooting range intervals as paid so they remain bookable.';

    public function handle(): int
    {
        $intervals = ShootingRangeInterval::query()
            ->where('is_active', true)
            ->where('source', ShootingRangeIntervalEvent::SOURCE_MANUAL)
            ->whereNull('datePaid')
            ->get();

        if ($this->option('dry-run')) {
            $this->info("Manual intervals to fix: {$intervals->count()}");

            return self::SUCCESS;
        }

        foreach ($intervals as $interval) {
            $interval->update([
                'datePaid' => now(),
                'payment_due_date' => null,
                'subscription_status' => 'active',
            ]);

            ShootingRangeIntervalArchive::where('shooting_range_interval_id', $interval->id)
                ->update([
                    'datePaid' => $interval->datePaid,
                    'payment_due_date' => null,
                    'subscription_status' => 'active',
                    'updated_at' => now(),
                ]);
        }

        $this->info("Fixed manual intervals: {$intervals->count()}");

        return self::SUCCESS;
    }
}
