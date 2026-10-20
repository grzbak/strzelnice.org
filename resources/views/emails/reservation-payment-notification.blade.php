@php
    $title = match ($eventType) {
        \App\Mail\ReservationPaymentNotificationMail::EVENT_CREATED => 'Rezerwacja oczekuje na płatność',
        \App\Mail\ReservationPaymentNotificationMail::EVENT_CONFIRMED => 'Płatność została potwierdzona',
        \App\Mail\ReservationPaymentNotificationMail::EVENT_EXPIRED => 'Płatność rezerwacji wygasła',
        default => 'Płatność rezerwacji',
    };

    $lead = match ($eventType) {
        \App\Mail\ReservationPaymentNotificationMail::EVENT_CREATED => 'Utworzono rezerwację i wygenerowano link do płatności.',
        \App\Mail\ReservationPaymentNotificationMail::EVENT_CONFIRMED => 'Płatność została poprawnie zaksięgowana, a rezerwacja zatwierdzona.',
        \App\Mail\ReservationPaymentNotificationMail::EVENT_EXPIRED => 'Czas na opłacenie rezerwacji minął, dlatego termin wrócił do puli dostępnych.',
        default => 'Poniżej znajdują się szczegóły rezerwacji.',
    };
@endphp

<!doctype html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
</head>
<body style="margin:0;padding:0;background:#111111;font-family:Arial,sans-serif;color:#222222;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#111111;padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="620" cellspacing="0" cellpadding="0" style="max-width:620px;width:100%;background:#ffffff;border-radius:8px;overflow:hidden;">
                    <tr>
                        <td style="background:#171717;padding:22px 26px;text-align:center;">
                            <img src="cid:strzelnice-logo" alt="strzelnice.org" style="max-width:180px;height:auto;display:inline-block;">
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px 30px;">
                            <h1 style="margin:0 0 12px;font-size:24px;line-height:1.25;color:#b91c1c;">{{ $title }}</h1>
                            <p style="margin:0 0 22px;color:#444444;line-height:1.55;">{{ $lead }}</p>

                            @if ($paymentUrl && $eventType === \App\Mail\ReservationPaymentNotificationMail::EVENT_CREATED)
                                <p style="margin:0 0 24px;">
                                    <a href="{{ $paymentUrl }}" style="display:inline-block;background:#b91c1c;color:#ffffff;text-decoration:none;padding:12px 18px;border-radius:4px;font-weight:bold;">
                                        Opłać rezerwację
                                    </a>
                                </p>
                            @endif

                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;font-size:14px;">
                                <tr>
                                    <td style="padding:8px 0;color:#777777;width:160px;">Strzelnica</td>
                                    <td style="padding:8px 0;font-weight:bold;">{{ $reservation->shootingRange?->name }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:8px 0;color:#777777;">Oś</td>
                                    <td style="padding:8px 0;">{{ $reservation->lane?->name }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:8px 0;color:#777777;">Termin</td>
                                    <td style="padding:8px 0;">
                                        {{ $reservation->start?->format('Y-m-d H:i') }}
                                        -
                                        {{ $reservation->end?->format('H:i') }}
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:8px 0;color:#777777;">Stanowiska</td>
                                    <td style="padding:8px 0;">{{ $reservation->positions_count }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:8px 0;color:#777777;">Kwota</td>
                                    <td style="padding:8px 0;font-weight:bold;">
                                        {{ number_format((float) $reservation->payment_amount, 2, ',', ' ') }}
                                        {{ $reservation->payment_currency }}
                                    </td>
                                </tr>
                                @if ($reservation->payment_expires_at && $eventType === \App\Mail\ReservationPaymentNotificationMail::EVENT_CREATED)
                                    <tr>
                                        <td style="padding:8px 0;color:#777777;">Opłać do</td>
                                        <td style="padding:8px 0;">{{ $reservation->payment_expires_at->format('Y-m-d H:i') }}</td>
                                    </tr>
                                @endif
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
