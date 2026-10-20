<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @include('emails.partials.dark-theme')
    <title>Wniosek dotyczący rezerwacji</title>
</head>
<body class="email-page" style="margin:0; padding:0; background-color:#111315; color:#f8fafc; font-family:Arial,Helvetica,sans-serif;">
    @php
        $reservation = $requestModel->reservation;
        $isCancel = $requestModel->type === \App\Models\ReservationChangeRequest::TYPE_CANCEL;
        $eventTitle = match ($eventType) {
            \App\Mail\ReservationChangeRequestMail::EVENT_SUBMITTED => $isCancel ? 'Nowy wniosek o anulowanie' : 'Nowy wniosek o zmianę',
            \App\Mail\ReservationChangeRequestMail::EVENT_APPROVED => $isCancel ? 'Anulowanie zatwierdzone' : 'Zmiana zatwierdzona',
            \App\Mail\ReservationChangeRequestMail::EVENT_REJECTED => $isCancel ? 'Anulowanie odrzucone' : 'Zmiana odrzucona',
            default => 'Wniosek dotyczący rezerwacji',
        };
        $intro = match ($eventType) {
            \App\Mail\ReservationChangeRequestMail::EVENT_SUBMITTED => $recipientType === 'staff'
                ? 'Klient złożył wniosek wymagający decyzji personelu.'
                : 'Twój wniosek został zapisany i oczekuje na decyzję personelu.',
            \App\Mail\ReservationChangeRequestMail::EVENT_APPROVED => 'Wniosek został zatwierdzony.',
            \App\Mail\ReservationChangeRequestMail::EVENT_REJECTED => 'Wniosek został odrzucony.',
            default => 'Przekazujemy informację o wniosku.',
        };
        $details = [
            'Klient' => $reservation->customer_name ?? $reservation->customer?->name ?? $reservation->user?->name ?? 'Brak danych',
            'E-mail' => $reservation->customer_email ?? $reservation->customer?->email ?? $reservation->user?->email ?? 'Brak danych',
            'Strzelnica' => $reservation->shootingRange?->name ?? 'Usunięta strzelnica',
            'Dotychczas' => ($requestModel->oldLane?->name ?? $reservation->lane?->name ?? 'Oś')
                .' | '.$requestModel->old_start?->format('d.m.Y H:i')
                .' - '.$requestModel->old_end?->format('H:i'),
        ];
        if (! $isCancel) {
            $details['Proponowana zmiana'] = ($requestModel->newLane?->name ?? 'Oś')
                .' | '.$requestModel->new_start?->format('d.m.Y H:i')
                .' - '.$requestModel->new_end?->format('H:i');
        }
        if ($requestModel->decision_note) {
            $details['Uwagi personelu'] = $requestModel->decision_note;
        }
    @endphp

    <table class="email-page" role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%; background-color:#111315;">
        <tr>
            <td align="center" style="padding:32px 12px;">
                <table class="email-card" role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%; max-width:600px; background-color:#1b1e21; border:1px solid #353b40; border-radius:8px;">
                    <tr>
                        <td align="center" style="padding:30px 28px 18px;">
                            <img src="cid:strzelnice-logo" width="250" alt="strzelnice.org" style="display:block; width:250px; max-width:100%; height:auto; border:0;">
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding:8px 28px 30px;">
                            <div style="display:inline-block; width:52px; height:52px; line-height:52px; margin-bottom:18px; color:#ffffff; background-color:#b42318; border-radius:50%; font-size:24px; font-weight:bold; text-align:center;">
                                &#9998;
                            </div>
                            <h1 style="margin:0 0 12px; color:#ffffff; font-size:26px; line-height:1.3; font-weight:700;">
                                {{ $eventTitle }}
                            </h1>
                            <p style="max-width:470px; margin:0 auto 24px; color:#d5dadd; font-size:15px; line-height:1.65;">
                                {{ $intro }}
                            </p>
                            <table class="email-panel" role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%; background-color:#25292d; border:1px solid #3b4146; border-radius:6px;">
                                @foreach ($details as $label => $value)
                                    <tr>
                                        <td style="width:34%; padding:11px 14px; color:#9ca3a8; font-size:13px; border-bottom:1px solid #3b4146;">
                                            {{ $label }}
                                        </td>
                                        <td style="padding:11px 14px; color:#f8fafc; font-size:14px; line-height:1.45; border-bottom:1px solid #3b4146;">
                                            {{ $value }}
                                        </td>
                                    </tr>
                                @endforeach
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding:18px 24px; color:#8d969d; background-color:#151719; border-top:1px solid #353b40; font-size:11px; line-height:1.5;">
                            &copy; {{ date('Y') }} strzelnice.org
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
