<x-app-layout>
    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />
    <div class="page-bg-2"></div>


    <div class="flex flex-col items-center pt-6">
        <div class="w-full px-6 py-4 mt-6 overflow-hidden bg-white shadow-md sm:max-w-md sm:rounded-lg">
            <h1 class="w-full mb-3 text-xl font-semibold text-center">{{ __('Log in') }}</h1>

            @if (session('pending_invitation_token'))
                <div class="p-3 mb-4 text-sm border rounded invitation-auth-notice">
                    <div class="d-flex gap-2 align-items-start">
                        <i class="mt-1 bi bi-envelope-check" aria-hidden="true"></i>
                        <div>
                            <strong>Akceptacja zaproszenia</strong>
                            <div>
                                Aby zaakceptować zaproszenie do strzelnicy, zaloguj się na konto przypisane do adresu
                                <strong>{{ session('pending_invitation_email') }}</strong>.
                            </div>
                        </div>
                    </div>
                </div>
            @endif
            @if (session('reservation_intent'))
                <div class="p-3 mb-4 text-sm border rounded invitation-auth-notice">
                    <div class="d-flex gap-2 align-items-start">
                        <i class="mt-1 bi bi-calendar-check" aria-hidden="true"></i>
                        <div>
                            <strong>Powrót do rezerwacji</strong>
                            <div>
                                Po zalogowaniu wrócisz do wybranego terminu, jeżeli wybór jest nadal aktualny.
                                Link rezerwacji jest zapamiętywany przez 30 minut.
                            </div>
                        </div>
                    </div>
                </div>
            @endif
            <form method="POST" action="{{ route('login') }}">
                @csrf

                <!-- Email Address -->
                <div class="mt-4">
                    <div class="flex rounded-md shadow-sm">
                        <span class="inline-flex items-center px-3 text-gray-500 bg-gray-100 border border-r-0 border-gray-300 rounded-l-md">
                            <i class="text-lg bi bi-at"></i>
                        </span>

                        <input
                            id="email"
                            name="email"
                            type="email"
                            required
                            autocomplete="username"
                            placeholder="{{__('Email')}}"
                            value="{{ old('email', session('pending_invitation_email')) }}"
                            class="block w-full border-gray-300 rounded-none rounded-r-md focus:border-indigo-500 focus:ring-indigo-500"
                        >
                    </div>

                    <x-input-error :messages="$errors->get('email')" class="mt-2" />
                </div>

                <!-- Password -->
                <div class="mt-4">
                    <div class="flex rounded-md shadow-sm">
                        <span class="inline-flex items-center px-3 text-gray-500 bg-gray-100 border border-r-0 border-gray-300 rounded-l-md">
                            <i class="text-lg bi bi-lock"></i>
                        </span>

                        <input
                            id="password"
                            name="password"
                            type="password"
                            required
                            autocomplete="new-password"
                            placeholder="{{__('Password')}}"
                            class="block w-full border-gray-300 rounded-none rounded-r-md focus:border-indigo-500 focus:ring-indigo-500"
                        >
                    </div>
                    <x-input-error :messages="$errors->get('password')" class="mt-2" />
                </div>

                <!-- Remember Me -->
                <div class="block mt-4">
                    <label for="remember_me" class="inline-flex items-center">
                        <input id="remember_me" type="checkbox" class="text-indigo-600 border-gray-300 rounded shadow-sm focus:ring-indigo-500" name="remember">
                        <span class="text-sm text-gray-600 ms-2">{{ __('Remember me') }}</span>
                    </label>
                </div>

                <div class="flex items-center justify-end mt-4">
                    @if (Route::has('password.request'))
                        <a class="text-sm text-gray-600 underline rounded-md hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" href="{{ route('password.request') }}">
                            {{ __('Forgot your password?') }}
                        </a>
                    @endif

                    <x-primary-button class="ms-3">
                        {{ __('Log in') }}
                    </x-primary-button>
                </div>
            </form>

            @if (Route::has('register'))
                <div class="pt-4 mt-2 text-sm text-center text-gray-600 border-t border-gray-200">
                    Nie masz jeszcze konta?
                    <a
                        href="{{ route('register') }}"
                        class="font-semibold text-indigo-600 rounded-md hover:text-indigo-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                    >
                        Załóż je TUTAJ!
                    </a>
                </div>
            @endif
        </div>
    </div>
    <style>
        .invitation-auth-notice {
            background: #fff8e1;
            border-color: #f1c40f !important;
            color: #3f3420;
        }

        .invitation-auth-notice i {
            color: #111;
            font-size: 1.1rem;
        }
    </style>
</x-app-layout>
