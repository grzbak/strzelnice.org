<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ShootingRangeInterval;
use Carbon\Carbon;

class CleanupExpiredShootingRangeIntervals extends Command
{
    protected $signature = 'shooting-range:cleanup-expired';
    protected $description = 'Usuwa wygasłe zakresy strzelnic i obsługuje alerty przed wygaśnięciem';

    public function handle()
{
    \Log::info('Cleanup running: '.now());

    $deleted = \App\Models\ShootingRangeInterval::where('end_date', '<', now())->delete();

    \Log::warning("Deleted rows: ".$deleted);

    return self::SUCCESS;
}
}

/*
|--------------------------------------------------------------------------
| MODYFIKACJE MODELU ShootingRangeInterval
|--------------------------------------------------------------------------
*/




/*
|--------------------------------------------------------------------------
| SCHEDULER (app/Console/Kernel.php)
|--------------------------------------------------------------------------
*/

/*
protected function schedule(Schedule $schedule)
{
    $schedule->command('shooting-range:cleanup-expired')
        ->dailyAt('02:00');
}
*/
