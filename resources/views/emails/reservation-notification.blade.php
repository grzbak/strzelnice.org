<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Informacja o rezerwacji</title>
</head>
<body style="margin:0; padding:0; background-color:#111315; color:#f8fafc; font-family:Arial,Helvetica,sans-serif;">
    @php
        $status = match ($reservation->status) {
            \App\Models\Reservation::STATUS_PENDING => 'Oczekująca',
            \App\Models\Reservation::STATUS_APPROVED => 'Zatwierdzona',
            \App\Models\Reservation::STATUS_CANCELLED => 'Anulowana',
            \App\Models\Reservation::STATUS_COMPLETED => 'Zrealizowana',
            \App\Models\Reservation::STATUS_NO_SHOW => 'Niezrealizowana',
            default => $reservation->status,
        };
        $eventTitle = match ($eventType) {
            \App\Mail\ReservationNotificationMail::EVENT_CREATED => 'Nowa rezerwacja',
            \App\Mail\ReservationNotificationMail::EVENT_UPDATED => 'Zmieniono rezerwację',
            \App\Mail\ReservationNotificationMail::EVENT_STATUS_CHANGED => 'Zmieniono status rezerwacji',
            default => 'Informacja o rezerwacji',
        };
        $intro = match ($eventType) {
            \App\Mail\ReservationNotificationMail::EVENT_CREATED => 'W systemie została złożona rezerwacja.',
            \App\Mail\ReservationNotificationMail::EVENT_UPDATED => 'Parametry rezerwacji zostały zmienione.',
            \App\Mail\ReservationNotificationMail::EVENT_STATUS_CHANGED => 'Status rezerwacji został zmieniony.',
            default => 'Przekazujemy informację o rezerwacji.',
        };
        $customerName = $reservation->customer_name
            ?? $reservation->customer?->name
            ?? $reservation->user?->name
            ?? 'Brak danych';
        $customerEmail = $reservation->customer_email
            ?? $reservation->customer?->email
            ?? $reservation->user?->email
            ?? 'Brak danych';
        $customerPhone = $reservation->customer_phone
            ?? $reservation->customer?->telephone
            ?? $reservation->user?->telephone
            ?? 'Brak danych';
        $details = [
            'Kto' => $customerName,
            'E-mail' => $customerEmail,
            'Telefon' => $customerPhone,
            'Kiedy' => $reservation->start->format('d.m.Y H:i').' - '.$reservation->end->format('H:i'),
            'Gdzie' => ($reservation->shootingRange?->name ?? 'Usunięta strzelnica').($reservation->shootingRange?->town ? ', '.$reservation->shootingRange->town : ''),
            'Co' => ($reservation->lane?->name ?? 'Usunięta oś').', stanowiska: '.$reservation->positions_count.($reservation->is_dynamic_shooting ? ', strzelanie dynamiczne' : ''),
            'Status' => $status,
        ];
    @endphp

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%; background-color:#111315;">
        <tr>
            <td align="center" style="padding:32px 12px;">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%; max-width:600px; background-color:#1b1e21; border:1px solid #353b40; border-radius:8px;">
                    <tr>
                        <td align="center" style="padding:30px 28px 18px;">
                            <img src="cid:strzelnice-logo" width="250" alt="strzelnice.org" style="display:block; width:250px; max-width:100%; height:auto; border:0;">
                        </td>
                    </tr>

                    <tr>
                        <td align="center" style="padding:8px 28px 30px;">
                            <div style="display:inline-block; width:52px; height:52px; line-height:52px; margin-bottom:18px; color:#ffffff; background-color:#b42318; border-radius:50%; font-size:24px; font-weight:bold; text-align:center;">
                                &#128197;
                            </div>

                            <h1 style="margin:0 0 12px; color:#ffffff; font-size:26px; line-height:1.3; font-weight:700;">
                                {{ $eventTitle }}
                            </h1>

                            <p style="max-width:470px; margin:0 auto 24px; color:#d5dadd; font-size:15px; line-height:1.65;">
                                {{ $intro }}
                            </p>

                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%; background-color:#25292d; border:1px solid #3b4146; border-radius:6px;">
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

                            @if ($recipientType === 'customer' && $reservation->status === \App\Models\Reservation::STATUS_PENDING)
                                <p style="margin:20px 0 0; color:#f3b2ad; font-size:13px; line-height:1.55;">
                                    Rezerwacja oczekuje na potwierdzenie przez personel strzelnicy.
                                </p>
                            @endif
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
