<?php

namespace App\Console\Commands;

use App\Models\ShootingRangeInvitation;
use Illuminate\Console\Command;

class ExpireShootingRangeInvitations extends Command
{
    protected $signature = 'shooting-ranges:expire-invitations';

    protected $description = 'Expires outdated shooting range invitations.';

    public function handle(): int
    {
        $count = ShootingRangeInvitation::whereIn('status', [
            ShootingRangeInvitation::STATUS_NONE,
            ShootingRangeInvitation::STATUS_PENDING,
        ])
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->update([
                'status' => ShootingRangeInvitation::STATUS_EXPIRED,
            ]);

        $this->info("Expired invitations: {$count}");

        return self::SUCCESS;
    }
}
