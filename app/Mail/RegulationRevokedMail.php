<?php

namespace App\Mail;

use App\Models\Regulation;
use Illuminate\Mail\Mailable;
use Symfony\Component\Mime\Email;

class RegulationRevokedMail extends Mailable
{
    public function __construct(public Regulation $regulation) {}

    public function build()
    {
        $logoPath = public_path('img/strzelnice_brand.png');

        return $this->subject('Regulamin został odwołany | strzelnice.org')
            ->view('emails.regulation-revoked')
            ->withSymfonyMessage(function (Email $message) use ($logoPath) {
                if (is_file($logoPath)) {
                    $message->embedFromPath($logoPath, 'strzelnice-logo', 'image/png');
                }
            });
    }
}
