<?php

namespace App\Mail;

use App\Models\Reservation;
use Illuminate\Mail\Mailable;
use Symfony\Component\Mime\Email;

class ReservationTodayReminderMail extends Mailable
{
    public function __construct(public Reservation $reservation)
    {
        $this->reservation->loadMissing([
            'shootingRange',
            'lane',
            'customer',
            'user',
        ]);
    }

    public function build()
    {
        $logoPath = public_path('img/strzelnice_brand.png');

        return $this->subject('Przypomnienie o dzisiejszej rezerwacji | strzelnice.org')
            ->view('emails.reservation-today-reminder')
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
