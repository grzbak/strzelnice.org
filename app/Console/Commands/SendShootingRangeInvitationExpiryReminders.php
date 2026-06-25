<?php

namespace App\Console\Commands;

use App\Mail\ShootingRangeInvitationExpiresSoonMail;
use App\Models\ShootingRangeInvitation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendShootingRangeInvitationExpiryReminders extends Command
{
    protected $signature = 'shooting-ranges:send-invitation-expiry-reminders {--days=2} {--dry-run}';

    protected $description = 'Sends reminders for shooting range invitations that expire soon.';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $now = now();
        $expiresBefore = $now->copy()->addDays($days);

        $invitations = ShootingRangeInvitation::query()
            ->with('shootingRange')
            ->where('status', ShootingRangeInvitation::STATUS_PENDING)
            ->whereNotNull('token')
            ->whereNotNull('expires_at')
            ->whereNull('expires_soon_notified_at')
            ->whereBetween('expires_at', [$now, $expiresBefore])
            ->get();

        if ($this->option('dry-run')) {
            $this->info("Invitations to notify: {$invitations->count()}");

            return self::SUCCESS;
        }

        foreach ($invitations as $invitation) {
            Mail::to($invitation->email)->send(new ShootingRangeInvitationExpiresSoonMail($invitation));

            $invitation->update([
                'expires_soon_notified_at' => now(),
            ]);
        }

        $this->info("Invitation expiry reminders sent: {$invitations->count()}");

        return self::SUCCESS;
    }
}
