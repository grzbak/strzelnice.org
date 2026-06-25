<?php

namespace App\Mail;

use App\Models\ShootingRangeInvitation;
use Illuminate\Mail\Mailable;
use Symfony\Component\Mime\Email;

class ShootingRangeInvitationMail extends Mailable
{
    public function __construct(public ShootingRangeInvitation $invitation)
    {
        $this->invitation->loadMissing('shootingRange');
    }

    public function build()
    {
        $logoPath = public_path('img/strzelnice_brand.png');

        return $this->subject('Zaproszenie do strzelnicy | strzelnice.org')
            ->view('emails.shooting-range-invitation')
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
