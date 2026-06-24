<?php

namespace App\Mail;

use App\Models\ShootingRangeInvitation;
use Illuminate\Mail\Mailable;

class ShootingRangeInvitationMail extends Mailable
{
    public function __construct(public ShootingRangeInvitation $invitation)
    {
    }

    public function build()
    {
        return $this->subject('Zaproszenie do strzelnicy')
            ->view('emails.shooting-range-invitation');
    }
}
