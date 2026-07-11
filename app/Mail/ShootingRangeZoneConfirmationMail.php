<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Symfony\Component\Mime\Email;

class ShootingRangeZoneConfirmationMail extends Mailable
{
    public function __construct(public array $formData) {}

    public function build()
    {
        $logoPath = public_path('img/strzelnice_brand.png');

        return $this->subject('Potwierdzenie zgłoszenia | strzelnice.org')
            ->view('emails.shooting-range-zone-confirmation')
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
