@php
    $isActivated = $status === \App\Mail\UserAccountStatusMail::STATUS_ACTIVATED;
    $isBlocked = $status === \App\Mail\UserAccountStatusMail::STATUS_BLOCKED;
    $isDeleted = $status === \App\Mail\UserAccountStatusMail::STATUS_DELETED;
    $title = match (true) {
        $isActivated => 'Twoje konto zostało aktywowane',
        $isBlocked => 'Twoje konto zostało zablokowane',
        $isDeleted => 'Twoje konto zostało usunięte',
        default => 'Informacja o koncie',
    };
    $icon = match (true) {
        $isActivated => '&#10003;',
        $isBlocked => '&#128274;',
        $isDeleted => '&#128465;',
        default => '&#8505;',
    };
    $intro = match (true) {
        $isActivated => 'Superadmin zatwierdził Twoje konto w serwisie strzelnice.org. Możesz korzystać z funkcji dostępnych dla Twojej roli.',
        $isBlocked => 'Dostęp do konta został ograniczony przez administratora systemu.',
        $isDeleted => 'Konto przypisane do adresu '.($user->email ?? '').' zostało usunięte z serwisu.',
        default => 'Przekazujemy informację dotyczącą Twojego konta w serwisie strzelnice.org.',
    };
@endphp

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @include('emails.partials.dark-theme')
    <title>Informacja o koncie</title>
</head>
<body class="email-page" style="margin:0; padding:0; background-color:#111315; color:#f8fafc; font-family:Arial,Helvetica,sans-serif;">
    <table class="email-page" role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%; background-color:#111315;">
        <tr>
            <td align="center" style="padding:32px 12px;">
                <table class="email-card" role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%; max-width:600px; background-color:#1b1e21; border:1px solid #353b40; border-radius:8px;">
                    <tr>
                        <td align="center" style="padding:30px 28px 18px;">
                            <img
                                src="cid:strzelnice-logo"
                                width="250"
                                alt="strzelnice.org"
                                style="display:block; width:250px; max-width:100%; height:auto; border:0;"
                            >
                        </td>
                    </tr>

                    <tr>
                        <td align="center" style="padding:8px 28px 30px;">
                            <div style="display:inline-block; width:52px; height:52px; line-height:52px; margin-bottom:18px; color:#ffffff; background-color:#b42318; border-radius:50%; font-size:24px; font-weight:bold; text-align:center;">
                                {!! $icon !!}
                            </div>

                            <h1 style="margin:0 0 12px; color:#ffffff; font-size:26px; line-height:1.3; font-weight:700;">
                                {{ $title }}
                            </h1>

                            <p style="margin:0 0 12px; color:#d5dadd; font-size:16px; line-height:1.65;">
                                Dzień dobry{{ ! empty($user->name) ? ', '.$user->name : '' }}!
                            </p>

                            <p style="max-width:470px; margin:0 auto 24px; color:#d5dadd; font-size:15px; line-height:1.65;">
                                {{ $intro }}
                            </p>

                            @if ($reason)
                                <table class="email-panel" role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%; margin-top:6px; background-color:#25292d; border:1px solid #3b4146; border-radius:6px;">
                                    <tr>
                                        <td style="width:34%; padding:11px 14px; color:#9ca3a8; font-size:13px; border-bottom:1px solid #3b4146;">
                                            Informacja administratora
                                        </td>
                                        <td style="padding:11px 14px; color:#f8fafc; font-size:14px; line-height:1.45; border-bottom:1px solid #3b4146;">
                                            {{ $reason }}
                                        </td>
                                    </tr>
                                </table>
                            @endif

                            @if ($isActivated)
                                <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                                    <tr>
                                        <td align="center" bgcolor="#b42318" style="border-radius:6px;">
                                            <a
                                                href="{{ route('login') }}"
                                                style="display:inline-block; padding:13px 24px; color:#ffffff; font-size:15px; font-weight:700; text-decoration:none; border-radius:6px;"
                                            >
                                                Przejdź do logowania
                                            </a>
                                        </td>
                                    </tr>
                                </table>
                            @endif

                            <p style="margin:24px 0 0; color:#9ca3a8; font-size:12px; line-height:1.55;">
                                Wiadomość została wysłana automatycznie. W razie wątpliwości skontaktuj się z administratorem serwisu.
                            </p>
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
