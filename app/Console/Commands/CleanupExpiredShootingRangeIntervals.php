<?php

namespace App\Console\Commands;

use App\Models\ShootingRangeInterval;
use App\Models\ShootingRangeIntervalArchive;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CleanupExpiredShootingRangeIntervals extends Command
{
    protected $signature = 'shooting-range:cleanup-expired {--dry-run}';

    protected $description = 'Usuwa wygasłe aktywne przedziały i dezaktywuje ich wpisy archiwalne.';

    public function handle(): int
    {
        $today = today()->toDateString();

        $expiredIntervals = ShootingRangeInterval::query()
            ->where('is_active', true)
            ->whereDate('end_date', '<', $today);

        $expiredArchives = ShootingRangeIntervalArchive::query()
            ->where('is_active', true)
            ->whereDate('end_date', '<', $today);

        $intervalsCount = (clone $expiredIntervals)->count();
        $archivesCount = (clone $expiredArchives)->count();

        if ($this->option('dry-run')) {
            $this->info("Aktywne przedziały do usunięcia: {$intervalsCount}");
            $this->info("Wpisy archiwalne do dezaktywacji: {$archivesCount}");

            return self::SUCCESS;
        }

        [$deletedIntervals, $deactivatedArchives] = DB::transaction(
            function () use ($expiredIntervals, $expiredArchives): array {
                $deactivatedArchives = $expiredArchives->update([
                    'is_active' => false,
                ]);

                $deletedIntervals = $expiredIntervals->delete();

                return [$deletedIntervals, $deactivatedArchives];
            }
        );

        Log::info('Expired shooting range intervals cleanup completed.', [
            'date' => $today,
            'deleted_intervals' => $deletedIntervals,
            'deactivated_archives' => $deactivatedArchives,
        ]);

        $this->info("Usunięte aktywne przedziały: {$deletedIntervals}");
        $this->info("Dezaktywowane wpisy archiwalne: {$deactivatedArchives}");

        return self::SUCCESS;
    }
}
