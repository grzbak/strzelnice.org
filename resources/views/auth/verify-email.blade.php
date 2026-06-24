<x-guest-layout>

    <section class="registration-message">

        <h1>Dziękujemy za rejestrację</h1>

        <p>
            Na podany adres e-mail wysłaliśmy link aktywacyjny.
            Kliknij go, aby potwierdzić konto i rozpocząć korzystanie z serwisu.
        </p>

        @if (session('status') === 'verification-link-sent')
            <div class="registration-message__status" role="status">
                <i class="bi bi-check-circle-fill"></i>
                Nowy link aktywacyjny został wysłany na Twój adres e-mail.
            </div>
        @endif

        <div class="registration-message__actions">
            <form method="POST" action="{{ route('verification.send') }}">
                @csrf

                <button type="submit" class="registration-message__primary">
                    <i class="bi bi-send"></i>
                    Wyślij link ponownie
                </button>
            </form>

            <form method="POST" action="{{ route('logout') }}">
                @csrf

                <button type="submit" class="registration-message__secondary">
                    Wyloguj się
                </button>
            </form>
        </div>
    </section>

    <style>
        .registration-message {
            width: min(100%, 560px);
            padding: 2.25rem;
            color: #f8fafc;
            text-align: center;
            background: #171a1d;
            border: 1px solid #353b40;
            border-radius: 8px;
            box-shadow: 0 18px 45px rgba(0, 0, 0, .28);
        }

        .registration-message__logo-wrap {
            display: flex;
            align-items: center;
            justify-content: center;
            width: min(100%, 300px);
            min-height: 72px;
            margin: 0 auto 1.5rem;
            padding: .7rem 1rem;
            background: #fff;
            border-radius: 6px;
        }

        .registration-message__logo {
            display: block;
            width: 100%;
            height: auto;
            max-height: 52px;
            object-fit: contain;
        }

        .registration-message__icon {
            display: grid;
            width: 52px;
            height: 52px;
            margin: 0 auto 1rem;
            color: #fff;
            font-size: 1.45rem;
            place-items: center;
            background: #b42318;
            border-radius: 50%;
        }

        .registration-message h1 {
            margin: 0 0 .75rem;
            color: #fff;
            font-size: 1.65rem;
            font-weight: 700;
        }

        .registration-message p {
            max-width: 440px;
            margin: 0 auto;
            color: #d5dadd;
            font-size: .95rem;
            line-height: 1.65;
        }

        .registration-message__status {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: .5rem;
            margin-top: 1.25rem;
            padding: .75rem 1rem;
            color: #d1fae5;
            font-size: .86rem;
            background: rgba(25, 135, 84, .2);
            border: 1px solid rgba(74, 222, 128, .35);
            border-radius: 6px;
        }

        .registration-message__actions {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: .75rem;
            margin-top: 1.5rem;
            flex-wrap: wrap;
        }

        .registration-message__primary,
        .registration-message__secondary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .45rem;
            min-height: 42px;
            padding: .65rem 1rem;
            font-size: .86rem;
            font-weight: 600;
            border-radius: 6px;
            transition: background-color .15s ease, border-color .15s ease;
        }

        .registration-message__primary {
            color: #fff;
            background: #b42318;
            border: 1px solid #b42318;
        }

        .registration-message__primary:hover {
            background: #931d14;
            border-color: #931d14;
        }

        .registration-message__secondary {
            color: #eef1f2;
            background: transparent;
            border: 1px solid #596168;
        }

        .registration-message__secondary:hover {
            background: #292e32;
            border-color: #7c858c;
        }

        @media (max-width: 575.98px) {
            .registration-message {
                padding: 1.5rem 1.1rem;
            }

            .registration-message__actions,
            .registration-message__actions form,
            .registration-message__primary,
            .registration-message__secondary {
                width: 100%;
            }
        }
    </style>
</x-guest-layout>
