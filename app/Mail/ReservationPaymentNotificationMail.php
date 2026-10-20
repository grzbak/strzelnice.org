<?php

namespace App\Mail;

use App\Models\Reservation;
use Illuminate\Mail\Mailable;
use Symfony\Component\Mime\Email;

class ReservationPaymentNotificationMail extends Mailable
{
    public const EVENT_CREATED = 'created';

    public const EVENT_CONFIRMED = 'confirmed';

    public const EVENT_EXPIRED = 'expired';

    public function __construct(
        public Reservation $reservation,
        public string $eventType,
        public string $recipientType,
        public ?string $paymentUrl = null
    ) {
        $this->reservation->loadMissing(['shootingRange', 'lane', 'payments']);
    }

    public function build()
    {
        $logoPath = public_path('img/strzelnice_brand.png');

        return $this->subject($this->subjectLine())
            ->view('emails.reservation-payment-notification')
            ->withSymfonyMessage(function (Email $message) use ($logoPath) {
                if (is_file($logoPath)) {
                    $message->embedFromPath($logoPath, 'strzelnice-logo', 'image/png');
                }
            });
    }

    private function subjectLine(): string
    {
        return match ($this->eventType) {
            self::EVENT_CREATED => 'Rezerwacja oczekuje na płatność | strzelnice.org',
            self::EVENT_CONFIRMED => 'Płatność rezerwacji potwierdzona | strzelnice.org',
            self::EVENT_EXPIRED => 'Płatność rezerwacji wygasła | strzelnice.org',
            default => 'Płatność rezerwacji | strzelnice.org',
        };
    }
}
