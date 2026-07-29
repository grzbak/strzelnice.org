<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @include('emails.partials.dark-theme')
    <title>Zmiana uprawnień w strzelnicy</title>
</head>
<body class="email-page" style="margin:0; padding:0; background-color:#111315; color:#f8fafc; font-family:Arial,Helvetica,sans-serif;">
    @php($isAssignment = $changeType === \App\Mail\ShootingRangeStaffRoleMail::CHANGE_ASSIGNED)
    @php($roleLabel = $staffRole === 'admin' ? 'administrator' : 'pracownik')

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
                                &#128100;
                            </div>

                            <h1 style="margin:0 0 12px; color:#ffffff; font-size:26px; line-height:1.3; font-weight:700;">
                                {{ $isAssignment ? 'Dodano Cię do strzelnicy' : 'Twoja rola została zmieniona' }}
                            </h1>

                            <p style="margin:0 0 12px; color:#d5dadd; font-size:16px; line-height:1.65;">
                                Dzień dobry, {{ $user->name }}!
                            </p>

                            <p style="max-width:470px; margin:0 auto 20px; color:#d5dadd; font-size:15px; line-height:1.65;">
                                @if ($isAssignment)
                                    Zostałeś przypisany do strzelnicy
                                    <strong>{{ $shootingRange->name }}</strong>
                                    jako <strong>{{ $roleLabel }}</strong>.
                                @else
                                    Twoja rola w strzelnicy
                                    <strong>{{ $shootingRange->name }}</strong>
                                    została zmieniona na <strong>{{ $roleLabel }}</strong>.
                                @endif
                            </p>

                            <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                                <tr>
                                    <td align="center" bgcolor="#b42318" style="border-radius:6px;">
                                        <a href="{{ route('login') }}" style="display:inline-block; padding:13px 24px; color:#ffffff; font-size:15px; font-weight:700; text-decoration:none; border-radius:6px;">
                                            Przejdź do logowania
                                        </a>
                                    </td>
                                </tr>
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
