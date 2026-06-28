<x-app-layout>
    <div class="page-bg-2"></div>

    <section class="container py-5">
        <x-session-alerts />

        <div class="p-4 bg-white shadow-sm contact-panel">
            <div class="row g-5">
                <div class="col-lg-5">
                    <h1 class="mb-4 h2">Kontakt</h1>

                    @if ($configuration)
                        <div class="mb-4">
                            @foreach ([
                                $configuration->company_name_1,
                                $configuration->company_name_2,
                                $configuration->company_name_3,
                            ] as $companyName)
                                @if ($companyName)
                                    <div class="fw-semibold">{{ $companyName }}</div>
                                @endif
                            @endforeach
                        </div>

                        @if ($configuration->street || $configuration->city)
                            <div class="mb-3 d-flex gap-3">
                                <i class="mt-1 bi bi-geo-alt text-danger"></i>
                                <div>
                                    @if ($configuration->street)
                                        <div>
                                            {{ $configuration->street }}
                                            {{ $configuration->building_number }}
                                            @if ($configuration->unit_number)
                                                /{{ $configuration->unit_number }}
                                            @endif
                                        </div>
                                    @endif
                                    <div>
                                        {{ $configuration->postal_code }}
                                        {{ $configuration->city }}
                                    </div>
                                    @if ($configuration->voivodeship)
                                        <div class="text-muted">{{ $configuration->voivodeship }}</div>
                                    @endif
                                </div>
                            </div>
                        @endif

                        @foreach ([
                            ['bi-telephone', 'tel:' . $configuration->phone_1, $configuration->phone_1],
                            ['bi-telephone', 'tel:' . $configuration->phone_2, $configuration->phone_2],
                            ['bi-printer', null, $configuration->fax],
                            ['bi-envelope', 'mailto:' . $configuration->email, $configuration->email],
                        ] as [$icon, $href, $value])
                            @if ($value)
                                <div class="mb-2 d-flex gap-3">
                                    <i class="bi {{ $icon }} text-danger"></i>
                                    @if ($href)
                                        <a href="{{ $href }}" class="text-decoration-none">{{ $value }}</a>
                                    @else
                                        <span>{{ $value }}</span>
                                    @endif
                                </div>
                            @endif
                        @endforeach

                        @if ($configuration->bank_name || $configuration->bank_account_number)
                            <div class="pt-3 mt-4 border-top">
                                <div class="mb-1 text-muted small">Dane bankowe</div>
                                @if ($configuration->bank_name)
                                    <div>{{ $configuration->bank_name }}</div>
                                @endif
                                @if ($configuration->bank_account_number)
                                    <div class="font-monospace">
                                        {{ $configuration->formatted_bank_account_number }}
                                    </div>
                                @endif
                            </div>
                        @endif
                    @else
                        <p class="text-muted">Dane kontaktowe nie zostały jeszcze skonfigurowane.</p>
                    @endif
                </div>

                <div class="col-lg-7">
                    <h2 class="mb-4 h4">Napisz do nas</h2>

                    <form
                        id="contactForm"
                        method="POST"
                        action="{{ route('contact.send') }}"
                        novalidate
                    >
                        @csrf
                        <input type="hidden" name="g-recaptcha-response" id="g-recaptcha-response">

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="name" class="form-label">Imię i nazwisko</label>
                                <input
                                    type="text"
                                    id="name"
                                    name="name"
                                    value="{{ old('name') }}"
                                    class="form-control @error('name') is-invalid @enderror"
                                    required
                                >
                                <x-input-error :messages="$errors->get('name')" class="mt-1" />
                            </div>

                            <div class="col-md-6">
                                <label for="email" class="form-label">E-mail</label>
                                <input
                                    type="email"
                                    id="email"
                                    name="email"
                                    value="{{ old('email') }}"
                                    class="form-control @error('email') is-invalid @enderror"
                                    required
                                >
                                <x-input-error :messages="$errors->get('email')" class="mt-1" />
                            </div>

                            <div class="col-md-6">
                                <label for="phone" class="form-label">Telefon</label>
                                <input
                                    type="text"
                                    id="phone"
                                    name="phone"
                                    value="{{ old('phone') }}"
                                    class="form-control @error('phone') is-invalid @enderror"
                                >
                                <x-input-error :messages="$errors->get('phone')" class="mt-1" />
                            </div>

                            <div class="col-md-6">
                                <label for="subject" class="form-label">Temat</label>
                                <input
                                    type="text"
                                    id="subject"
                                    name="subject"
                                    value="{{ old('subject') }}"
                                    class="form-control @error('subject') is-invalid @enderror"
                                    required
                                >
                                <x-input-error :messages="$errors->get('subject')" class="mt-1" />
                            </div>

                            <div class="col-12">
                                <label for="message" class="form-label">Wiadomość</label>
                                <textarea
                                    id="message"
                                    name="message"
                                    rows="7"
                                    class="form-control @error('message') is-invalid @enderror"
                                    required
                                >{{ old('message') }}</textarea>
                                <x-input-error :messages="$errors->get('message')" class="mt-1" />
                            </div>
                        </div>

                        <x-input-error :messages="$errors->get('g-recaptcha-response')" class="mt-3" />

                        <button type="submit" class="mt-4 btn btn-danger">
                            <i class="bi bi-send me-1"></i>
                            Wyślij wiadomość
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <style>
        .contact-panel {
            background-color: rgba(255, 255, 255, 0.88) !important;
        }
    </style>

    <script src="https://www.google.com/recaptcha/api.js?render={{ config('services.recaptcha.site_key') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('contactForm');
            const tokenInput = document.getElementById('g-recaptcha-response');

            form.addEventListener('submit', function (event) {
                event.preventDefault();

                if (!form.checkValidity()) {
                    form.reportValidity();
                    return;
                }

                grecaptcha.ready(function () {
                    grecaptcha.execute(@json(config('services.recaptcha.site_key')), {
                        action: 'contact'
                    }).then(function (token) {
                        tokenInput.value = token;
                        form.submit();
                    });
                });
            });
        });
    </script>
</x-app-layout>
