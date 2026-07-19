@php
    $isActivated = $status === \App\Mail\UserAccountStatusMail::STATUS_ACTIVATED;
    $isBlocked = $status === \App\Mail\UserAccountStatusMail::STATUS_BLOCKED;
    $isDeleted = $status === \App\Mail\UserAccountStatusMail::STATUS_DELETED;
@endphp

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Informacja o koncie</title>
</head>
<body style="margin:0;padding:0;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
    <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background:#f3f4f6;padding:32px 12px;">
        <tr>
            <td align="center">
                <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="max-width:640px;background:#ffffff;border-radius:14px;overflow:hidden;border:1px solid #e5e7eb;">
                    <tr>
                        <td style="background:#111827;padding:28px 32px;text-align:center;">
                            <img src="cid:strzelnice-logo" alt="strzelnice.org" style="max-width:180px;height:auto;">
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:32px;">
                            <p style="margin:0 0 10px;font-size:15px;color:#6b7280;">
                                Dzien dobry {{ $user->name ?? '' }},
                            </p>

                            @if ($isActivated)
                                <h1 style="margin:0 0 16px;font-size:24px;line-height:1.25;color:#111827;">
                                    Twoje konto zostalo aktywowane
                                </h1>
                                <p style="margin:0 0 20px;font-size:16px;line-height:1.6;">
                                    Superadmin zatwierdzil Twoje konto w serwisie strzelnice.org.
                                    Mozesz korzystac z funkcji dostepnych dla Twojej roli.
                                </p>
                            @elseif ($isBlocked)
                                <h1 style="margin:0 0 16px;font-size:24px;line-height:1.25;color:#111827;">
                                    Twoje konto zostalo zablokowane
                                </h1>
                                <p style="margin:0 0 20px;font-size:16px;line-height:1.6;">
                                    Dostep do konta zostal ograniczony przez administratora systemu.
                                </p>
                            @elseif ($isDeleted)
                                <h1 style="margin:0 0 16px;font-size:24px;line-height:1.25;color:#111827;">
                                    Twoje konto zostalo usuniete
                                </h1>
                                <p style="margin:0 0 20px;font-size:16px;line-height:1.6;">
                                    Konto przypisane do adresu {{ $user->email ?? '' }} zostalo usuniete z serwisu.
                                </p>
                            @endif

                            @if ($reason)
                                <div style="margin:22px 0;padding:16px;border-left:4px solid #dc2626;background:#fef2f2;border-radius:8px;">
                                    <strong style="display:block;margin-bottom:6px;color:#991b1b;">Informacja administratora</strong>
                                    <span style="font-size:15px;line-height:1.5;color:#374151;">{{ $reason }}</span>
                                </div>
                            @endif

                            <p style="margin:24px 0 0;font-size:14px;line-height:1.6;color:#6b7280;">
                                Wiadomosc zostala wyslana automatycznie. W razie watpliwosci skontaktuj sie z administratorem serwisu.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:18px 32px;background:#f9fafb;color:#6b7280;font-size:12px;text-align:center;">
                            strzelnice.org
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
