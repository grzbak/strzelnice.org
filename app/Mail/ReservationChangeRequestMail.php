<?php

namespace App\Mail;

use App\Models\ReservationChangeRequest;
use Illuminate\Mail\Mailable;
use Symfony\Component\Mime\Email;

class ReservationChangeRequestMail extends Mailable
{
    public const EVENT_SUBMITTED = 'submitted';

    public const EVENT_APPROVED = 'approved';

    public const EVENT_REJECTED = 'rejected';

    public function __construct(
        public ReservationChangeRequest $requestModel,
        public string $eventType,
        public string $recipientType
    ) {
        $this->requestModel->loadMissing([
            'reservation.shootingRange',
            'reservation.lane',
            'requester',
            'decider',
            'oldLane',
            'newLane',
        ]);
    }

    public function build()
    {
        $logoPath = public_path('img/strzelnice_brand.png');

        return $this->subject($this->subjectLine())
            ->view('emails.reservation-change-request')
            ->withSymfonyMessage(function (Email $message) use ($logoPath) {
                if (is_file($logoPath)) {
                    $message->embedFromPath($logoPath, 'strzelnice-logo', 'image/png');
                }
            });
    }

    private function subjectLine(): string
    {
        $action = $this->requestModel->type === ReservationChangeRequest::TYPE_CANCEL
            ? 'anulowania rezerwacji'
            : 'zmiany rezerwacji';

        return match ($this->eventType) {
            self::EVENT_SUBMITTED => 'Nowy wniosek '.$action.' | strzelnice.org',
            self::EVENT_APPROVED => 'Wniosek '.$action.' zatwierdzony | strzelnice.org',
            self::EVENT_REJECTED => 'Wniosek '.$action.' odrzucony | strzelnice.org',
            default => 'Wniosek dotyczący rezerwacji | strzelnice.org',
        };
    }
}
