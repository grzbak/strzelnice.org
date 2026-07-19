<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Symfony\Component\Mime\Email;

class UserAccountStatusMail extends Mailable
{
    public const STATUS_ACTIVATED = 'activated';

    public const STATUS_BLOCKED = 'blocked';

    public const STATUS_DELETED = 'deleted';

    public function __construct(
        public object $user,
        public string $status,
        public ?string $reason = null
    ) {}

    public function build()
    {
        $logoPath = public_path('img/strzelnice_brand.png');

        $subject = match ($this->status) {
            self::STATUS_ACTIVATED => 'Konto zostało aktywowane | strzelnice.org',
            self::STATUS_BLOCKED => 'Konto zostało zablokowane | strzelnice.org',
            self::STATUS_DELETED => 'Konto zostało usunięte | strzelnice.org',
            default => 'Informacja o koncie | strzelnice.org',
        };

        return $this->subject($subject)
            ->view('emails.user-account-status')
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
