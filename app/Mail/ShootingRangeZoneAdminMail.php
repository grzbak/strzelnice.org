<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Symfony\Component\Mime\Email;

class ShootingRangeZoneAdminMail extends Mailable
{
    public function __construct(public array $formData) {}

    public function build()
    {
        $logoPath = public_path('img/strzelnice_brand.png');

        return $this->replyTo($this->formData['email'], $this->formData['name'])
            ->subject('Strefa strzelnic: nowe zgłoszenie')
            ->view('emails.shooting-range-zone-admin')
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
