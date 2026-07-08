<x-app-layout>
<div class="page-bg-2"></div>

    @include('components.breadcrumb')


    <div class="flex flex-col items-center pt-6">
        <div class="w-full px-6 py-4 mt-6 overflow-hidden bg-white shadow-md sm:max-w-md sm:rounded-lg">
            <h1 class="w-full mb-3 text-xl font-semibold text-center">
                @if($role=='klient')
                    {{__('Register')}}
                @else
                    {{__('Instalation')}}
                @endif
            </h1>

            @if (session('pending_invitation_token'))
                <div class="p-3 mb-4 text-sm border rounded invitation-auth-notice">
                    <div class="d-flex gap-2 align-items-start">
                        <i class="mt-1 bi bi-envelope-check" aria-hidden="true"></i>
                        <div>
                            <strong>Akceptacja zaproszenia</strong>
                            <div>
                                Aby zaakceptować zaproszenie do strzelnicy, zarejestruj konto na adres
                                <strong>{{ session('pending_invitation_email') }}</strong>.
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <form id="contactForm" method="POST" action="{{ route('register') }}">
                @csrf

                @if($role=='superadmin')
                    <input id="role" name="role" type="hidden" value="superadmin">
                    <input id="is_approved" name="is_approved" type="hidden" value="1">
                @endif

                <!-- Name -->
                <div>
                    <div class="flex rounded-md shadow-sm">
                        <span class="inline-flex items-center px-3 text-gray-500 bg-gray-100 border border-r-0 border-gray-300 rounded-l-md">
                            <i class="text-lg bi bi-person"></i>
                        </span>

                        <input
                            id="name"
                            name="name"
                            type="text"
                            required
                            autofocus
                            autocomplete="name"
                            placeholder="{{__('Name')}}"
                            value="{{ old('name') }}"
                            class="block w-full border-gray-300 rounded-none rounded-r-md focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                </div>

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


                <!-- Confirm Password -->
                <div class="mt-4">
                    <div class="flex rounded-md shadow-sm">
                        <span class="inline-flex items-center px-3 text-gray-500 bg-gray-100 border border-r-0 border-gray-300 rounded-l-md">
                            <i class="text-lg bi bi-lock"></i>
                        </span>

                        <input
                            id="password"
                            name="password_confirmation"
                            type="password"
                            required
                            autocomplete="new-password"
                            placeholder="{{__('Confirm Password')}}"
                            class="block w-full border-gray-300 rounded-none rounded-r-md focus:border-indigo-500 focus:ring-indigo-500"
                        >
                    </div>
                    <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
                </div>


                <!-- Telephone -->
                <div class="mt-4">
                    <div class="flex rounded-md shadow-sm">
                        <span class="inline-flex items-center px-3 text-gray-500 bg-gray-100 border border-r-0 border-gray-300 rounded-l-md">
                            <i class="text-lg bi bi-lock"></i>
                        </span>

                        <input
                            id="telephone"
                            name="telephone"
                            type="text"
                            autocomplete="telephone"
                            placeholder="{{__('Telephone')}}"
                            class="block w-full border-gray-300 rounded-none rounded-r-md focus:border-indigo-500 focus:ring-indigo-500"
                        >
                    </div>
                    <x-input-error :messages="$errors->get('telephone')" class="mt-2" />
                </div>
                <div>
                    <input type="hidden" name="g-recaptcha-response" id="g-recaptcha-response">

                    @error('g-recaptcha-response')
                        <div class="mb-3 text-danger">{{ $message }}</div>
                    @enderror
                </div>

                <div class="flex items-center justify-end mt-4">
                    <a class="text-sm text-gray-600 underline rounded-md hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" href="{{ route('login') }}">
                        {{ __('Already registered?') }}
                    </a>

                    <x-primary-button class="ms-4">
                        {{ __('Register') }}
                    </x-primary-button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://www.google.com/recaptcha/api.js?render={{ config('services.recaptcha.site_key') }}"></script>

    <script>
        document.getElementById('contactForm').addEventListener('submit', function (e) {
            e.preventDefault();

            grecaptcha.ready(function () {
                grecaptcha.execute('{{ config('services.recaptcha.site_key') }}', { action: 'kontakt' })
                    .then(function (token) {
                        document.getElementById('g-recaptcha-response').value = token;
                        document.getElementById('contactForm').submit();
                    });
            });
        });
    </script>
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
