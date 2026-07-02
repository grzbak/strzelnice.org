<?php

namespace App\Mail;

use App\Models\ShootingRange;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Symfony\Component\Mime\Email;

class ShootingRangeStaffRoleMail extends Mailable
{
    public const CHANGE_ASSIGNED = 'assigned';

    public const CHANGE_ROLE_UPDATED = 'role_updated';

    public function __construct(
        public User $user,
        public ShootingRange $shootingRange,
        public string $staffRole,
        public string $changeType
    ) {}

    public function build()
    {
        $logoPath = public_path('img/strzelnice_brand.png');
        $subject = $this->changeType === self::CHANGE_ASSIGNED
            ? 'Dodano Cię do personelu strzelnicy | strzelnice.org'
            : 'Zmieniono Twoją rolę w strzelnicy | strzelnice.org';

        return $this->subject($subject)
            ->view('emails.shooting-range-staff-role')
            ->withSymfonyMessage(function (Email $message) use ($logoPath) {
                if (is_file($logoPath)) {
                    $message->embedFromPath(
                        $logoPath,
                        'strzelnice-logo',
                        'image/png'
                    );
                }
            });
    }
}
