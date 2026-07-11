<?php

namespace App\Mail;

use App\Models\Reservation;
use Illuminate\Mail\Mailable;
use Symfony\Component\Mime\Email;

class ReservationNotificationMail extends Mailable
{
    public const EVENT_CREATED = 'created';

    public const EVENT_UPDATED = 'updated';

    public const EVENT_STATUS_CHANGED = 'status_changed';

    public function __construct(
        public Reservation $reservation,
        public string $eventType,
        public string $recipientType
    ) {
        $this->reservation->loadMissing([
            'shootingRange',
            'lane',
            'customer',
            'user',
            'creator',
            'approver',
            'canceller',
            'finalizer',
        ]);
    }

    public function build()
    {
        $logoPath = public_path('img/strzelnice_brand.png');

        return $this->subject($this->subjectLine())
            ->view('emails.reservation-notification')
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

    private function subjectLine(): string
    {
        $prefix = match ($this->eventType) {
            self::EVENT_CREATED => 'Nowa rezerwacja',
            self::EVENT_UPDATED => 'Zmieniono rezerwację',
            self::EVENT_STATUS_CHANGED => 'Zmieniono status rezerwacji',
            default => 'Informacja o rezerwacji',
        };

        return $prefix.' | strzelnice.org';
    }
}
