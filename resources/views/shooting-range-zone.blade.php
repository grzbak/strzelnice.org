<x-app-layout>
    <main class="ssz-main">
        <section class="ssz-hero">
            <div class="ssz-hero-grid"></div>
            <div class="ssz-hero-rings" aria-hidden="true">
                <svg viewBox="0 0 400 400" fill="none">
                    <circle cx="200" cy="200" r="190" stroke="#ff3131" stroke-width="0.75" opacity="0.5"/>
                    <circle cx="200" cy="200" r="150" stroke="#ff3131" stroke-width="0.75" opacity="0.55"/>
                    <circle cx="200" cy="200" r="108" stroke="#ff3131" stroke-width="0.75" opacity="0.65"/>
                    <circle cx="200" cy="200" r="64" stroke="#ff3131" stroke-width="0.75" opacity="0.8"/>
                    <circle cx="200" cy="200" r="22" stroke="#ff5c5c" stroke-width="1.25" opacity="0.95"/>
                    <line x1="200" y1="0" x2="200" y2="400" stroke="#ff3131" stroke-width="0.5" opacity="0.35"/>
                    <line x1="0" y1="200" x2="400" y2="200" stroke="#ff3131" stroke-width="0.5" opacity="0.35"/>
                </svg>
            </div>

            <div class="ssz-wrap">
                <x-session-alerts />

                <div class="ssz-hero-content">
                    <div class="ssz-eyebrow">Strefa strzelnic</div>
                    <h1>Twoja strzelnica,<br>w zasięgu <em>każdego</em><br>strzelca w Polsce.</h1>
                    <p class="ssz-lede">
                        Podstrona, kalendarz rezerwacji i panel zarządzania bez konieczności posiadania własnej strony www.
                        Do tego materiały marketingowe na strzelnice, żeby klient mógł łatwo i szybko zarezerwować kolejny termin.
                    </p>
                    <div class="ssz-hero-actions">
                        <button class="ssz-btn ssz-btn-primary" type="button" data-ssz-form-toggle="ssz-form-hero">
                            Zarejestruj strzelnicę
                        </button>
                        <a class="ssz-btn ssz-btn-ghost" href="#jak-to-dziala">Zobacz, jak to działa</a>
                    </div>

                    <form
                        id="ssz-form-hero"
                        class="ssz-inline-form"
                        method="POST"
                        action="{{ route('shooting-range-zone.send') }}"
                        style="{{ old('source') === 'hero' ? '' : 'display:none;' }}"
                    >
                        @csrf
                        <input type="hidden" name="source" value="hero">
                        <input type="text" name="name" value="{{ old('source') === 'hero' ? old('name') : '' }}" placeholder="Imię i nazwisko *" required class="ssz-field @error('name') ssz-error @enderror">
                        <input type="email" name="email" value="{{ old('source') === 'hero' ? old('email') : '' }}" placeholder="E-mail *" required class="ssz-field @error('email') ssz-error @enderror">
                        <input type="tel" name="phone" value="{{ old('source') === 'hero' ? old('phone') : '' }}" placeholder="Telefon" class="ssz-field @error('phone') ssz-error @enderror">
                        <input type="text" name="shooting_range_name" value="{{ old('source') === 'hero' ? old('shooting_range_name') : '' }}" placeholder="Nazwa strzelnicy *" required class="ssz-field @error('shooting_range_name') ssz-error @enderror">
                        <input type="text" name="city" value="{{ old('source') === 'hero' ? old('city') : '' }}" placeholder="Miasto" class="ssz-field @error('city') ssz-error @enderror">
                        <textarea name="message" rows="3" placeholder="Wiadomość" class="ssz-field @error('message') ssz-error @enderror">{{ old('source') === 'hero' ? old('message') : '' }}</textarea>
                        <button class="ssz-btn ssz-btn-primary" type="submit">Wyślij zgłoszenie</button>
                    </form>

                    @if ($errors->any() && old('source') === 'hero')
                        <div class="ssz-form-errors">
                            Popraw błędy w formularzu i spróbuj ponownie.
                        </div>
                    @endif

                    <div class="ssz-hero-stats">
                        <div>
                            <div class="ssz-stat-num">24/7</div>
                            <div class="ssz-stat-label">Rezerwacje online</div>
                        </div>
                        <div>
                            <div class="ssz-stat-num">0%</div>
                            <div class="ssz-stat-label">Prowizji od rezerwacji</div>
                        </div>
                        <div>
                            <div class="ssz-stat-num">QR</div>
                            <div class="ssz-stat-label">Materiały w cenie</div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section id="jak-to-dziala">
            <div class="ssz-wrap">
                <div class="ssz-section-head">
                    <div class="ssz-eyebrow">Jak to działa</div>
                    <h2>Trzy kroki dzielą Cię od pierwszej rezerwacji</h2>
                    <p>
                        Cały proces uruchomienia odbywa się zdalnie. Nie potrzebujesz własnej strony,
                        programisty ani znajomości technicznej obsługi systemów rezerwacji.
                    </p>
                </div>

                <div class="ssz-steps">
                    <div class="ssz-step">
                        <div class="ssz-step-index">01 / Zgłoszenie</div>
                        <h3>Skontaktuj się z nami</h3>
                        <p>Wysyłasz zgłoszenie, a my kontaktujemy się z Tobą w sprawie uruchomienia strzelnicy w serwisie.</p>
                    </div>
                    <div class="ssz-step">
                        <div class="ssz-step-index">02 / Konfiguracja</div>
                        <h3>Wypełniasz podstronę i kalendarz</h3>
                        <p>Dodajesz opis, zdjęcia, osie i godziny dostępności stanowisk. Sam decydujesz, które terminy są dostępne online.</p>
                    </div>
                    <div class="ssz-step">
                        <div class="ssz-step-index">03 / Start</div>
                        <h3>Pojawiasz się w wyszukiwarce</h3>
                        <p>Twoja strzelnica trafia do wyszukiwarki strzelnice.org, a Ty otrzymujesz materiały marketingowe z kodem QR.</p>
                    </div>
                </div>
            </div>
        </section>

        <section id="funkcjonalnosci">
            <div class="ssz-wrap">
                <div class="ssz-section-head">
                    <div class="ssz-eyebrow">Co zyskujesz</div>
                    <h2>Wszystko, czego potrzebuje strzelnica do przyjmowania rezerwacji online</h2>
                </div>

                <div class="ssz-features">
                    @foreach ([
                        ['bi-calendar3', 'Kalendarz rezerwacji', 'Klienci widzą wolne terminy w czasie rzeczywistym i rezerwują stanowisko bez telefonów i wiadomości.'],
                        ['bi-geo-alt', 'Obecność w wyszukiwarce', 'Twoja strzelnica jest widoczna dla osób szukających terminu w Twoim mieście i województwie.'],
                        ['bi-file-earmark-text', 'Własna podstrona', 'Opis, zdjęcia, cennik stanowisk i regulamin w jednym miejscu, gotowe do udostępnienia klientom.'],
                        ['bi-window-sidebar', 'Panel zarządzania', 'Otwierasz i zamykasz terminy, zmieniasz dostępność stanowisk samodzielnie, z dowolnego urządzenia.'],
                        ['bi-graph-up-arrow', 'Brak progu wejścia', 'Zero instalacji i zero integracji technicznych po Twojej stronie. Wszystko działa w przeglądarce.'],
                        ['bi-receipt', 'Przejrzyste rozliczenia', 'Jedna, stała faktura miesięczna na przelew. Bez prowizji od rezerwacji i ukrytych opłat.'],
                    ] as [$icon, $title, $text])
                        <div class="ssz-feature">
                            <i class="bi {{ $icon }} ssz-feature-icon"></i>
                            <h3>{{ $title }}</h3>
                            <p>{{ $text }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="ssz-marketing" id="materialy">
            <div class="ssz-wrap">
                <div class="ssz-section-head">
                    <div class="ssz-eyebrow">W zestawie startowym</div>
                    <h2>Promujemy Cię nie tylko online</h2>
                    <p>
                        Każda zarejestrowana strzelnica otrzymuje fizyczne materiały,
                        które ułatwiają klientom rezerwację na miejscu.
                    </p>
                </div>

                <div class="ssz-marketing-grid">
                    <div class="ssz-marketing-card">
                        <span class="ssz-marketing-tag">Na stanowiska</span>
                        <h3>Naklejki z kodem QR</h3>
                        <p>Klient skanuje kod telefonem i od razu trafia do systemu rezerwacji Twojej strzelnicy.</p>
                    </div>
                    <div class="ssz-marketing-card">
                        <span class="ssz-marketing-tag">Dla stałych klientów</span>
                        <h3>Spersonalizowany baner z kodem QR</h3>
                        <p>Baner z kodem prowadzącym prosto do podstrony Twojej strzelnicy, gotowy do powieszenia przy wejściu lub recepcji.</p>
                    </div>
                </div>

                <p class="ssz-marketing-note">Materiały otrzymujesz w ramach abonamentu, bez dodatkowych opłat za projekt i druk.</p>
            </div>
        </section>

        <section class="ssz-offer" id="oferta">
            <div class="ssz-wrap">
                <div class="ssz-section-head">
                    <div class="ssz-eyebrow">Oferta</div>
                    <h2>Jeden, prosty abonament</h2>
                    <p>Bez prowizji od rezerwacji, bez kosztów wdrożenia, bez umowy na czas określony.</p>
                </div>

                <div class="ssz-offer-grid">
                    <div class="ssz-offer-left">
                        <h3>Co wchodzi w abonament</h3>
                        <ul class="ssz-offer-list">
                            <li><span>→</span> Podstrona strzelnicy w serwisie strzelnice.org</li>
                            <li><span>→</span> Kalendarz rezerwacji online dla nieograniczonej liczby stanowisk</li>
                            <li><span>→</span> Widoczność w wyszukiwarce wg miasta, województwa i terminu</li>
                            <li><span>→</span> Panel zarządzania dostępnością i rezerwacjami</li>
                            <li><span>→</span> Powiadomienia o nowych rezerwacjach</li>
                            <li><span>→</span> Naklejki QR na stanowiska i spersonalizowany baner</li>
                            <li><span>→</span> Wsparcie techniczne przy wdrożeniu i bieżącej obsłudze</li>
                        </ul>
                    </div>

                    <div class="ssz-offer-right">
                        <div class="ssz-price-tag">Abonament miesięczny</div>
                        <div class="ssz-price">100 zł<span> netto / mies.</span></div>
                        <div class="ssz-price-sub">
                            Faktura wystawiana raz w miesiącu. Płatność przelewem. Dla klientów strzelnicy korzystanie z serwisu jest bezpłatne.
                        </div>

                        <button class="ssz-btn ssz-btn-primary" type="button" data-ssz-form-toggle="ssz-form-offer">
                            Zarejestruj strzelnicę
                        </button>

                        <form
                            id="ssz-form-offer"
                            class="ssz-inline-form"
                            method="POST"
                            action="{{ route('shooting-range-zone.send') }}"
                            style="{{ old('source') === 'offer' ? '' : 'display:none;' }}"
                        >
                            @csrf
                            <input type="hidden" name="source" value="offer">
                            <input type="text" name="name" value="{{ old('source') === 'offer' ? old('name') : '' }}" placeholder="Imię i nazwisko *" required class="ssz-field @error('name') ssz-error @enderror">
                            <input type="email" name="email" value="{{ old('source') === 'offer' ? old('email') : '' }}" placeholder="E-mail *" required class="ssz-field @error('email') ssz-error @enderror">
                            <input type="tel" name="phone" value="{{ old('source') === 'offer' ? old('phone') : '' }}" placeholder="Telefon" class="ssz-field @error('phone') ssz-error @enderror">
                            <input type="text" name="shooting_range_name" value="{{ old('source') === 'offer' ? old('shooting_range_name') : '' }}" placeholder="Nazwa strzelnicy *" required class="ssz-field @error('shooting_range_name') ssz-error @enderror">
                            <input type="text" name="city" value="{{ old('source') === 'offer' ? old('city') : '' }}" placeholder="Miasto" class="ssz-field @error('city') ssz-error @enderror">
                            <textarea name="message" rows="3" placeholder="Wiadomość" class="ssz-field @error('message') ssz-error @enderror">{{ old('source') === 'offer' ? old('message') : '' }}</textarea>
                            <button class="ssz-btn ssz-btn-primary" type="submit">Wyślij zgłoszenie</button>
                        </form>

                        @if ($errors->any() && old('source') === 'offer')
                            <div class="ssz-form-errors">
                                Popraw błędy w formularzu i spróbuj ponownie.
                            </div>
                        @endif

                        <a class="ssz-btn ssz-btn-ghost" href="/kontakt">Porozmawiaj z nami</a>
                        <div class="ssz-offer-fine">Bez zobowiązania długoterminowego, rezygnacja z miesięcznym wypowiedzeniem.</div>
                    </div>
                </div>
            </div>
        </section>

        <section id="faq">
            <div class="ssz-wrap">
                <div class="ssz-section-head">
                    <div class="ssz-eyebrow">Pytania</div>
                    <h2>Najczęstsze pytania właścicieli strzelnic</h2>
                </div>

                <div class="ssz-faq">
                    @foreach ([
                        ['Czy muszę coś instalować na swojej stronie internetowej?', 'Nie. Cały system działa w ramach strzelnice.org. Możesz dodatkowo umieścić link do swojej podstrony na własnej stronie, ale nie jest to wymagane.'],
                        ['Czy klienci płacą za rezerwację przez serwis?', 'Nie, korzystanie z wyszukiwarki i systemu rezerwacji jest dla klientów bezpłatne. Rozliczenia za skorzystanie ze stanowiska ustalasz bezpośrednio jako strzelnica.'],
                        ['Jak wygląda płatność za korzystanie z systemu?', 'Co miesiąc wystawiamy jedną fakturę z terminem płatności przelewem. Nie pobieramy prowizji od liczby rezerwacji.'],
                        ['Czy mogę samodzielnie zamykać terminy, np. na czas zawodów?', 'Tak. W panelu zarządzania decydujesz, które terminy i stanowiska są otwarte do rezerwacji online, a które wyłączone.'],
                        ['Czy naklejki i baner są wliczone w cenę?', 'Tak. Projekt, druk i wysyłka naklejek QR oraz spersonalizowanego banera są wliczone w abonament.'],
                        ['Ile trwa uruchomienie podstrony?', 'Po rejestracji i weryfikacji zgłoszenia uzupełniasz dane strzelnicy. W większości przypadków podstrona może być gotowa do publikacji tego samego dnia.'],
                    ] as [$question, $answer])
                        <div class="ssz-faq-item">
                            <button class="ssz-faq-q" type="button">
                                {{ $question }}
                                <span>+</span>
                            </button>
                            <div class="ssz-faq-a"><p>{{ $answer }}</p></div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="ssz-final-cta" id="kontakt">
            <div class="ssz-hero-grid"></div>
            <div class="ssz-wrap ssz-final-cta-content">
                <h2>Otwórz drzwi swojej strzelnicy dla nowych klientów</h2>
                <p>Dołącz do strzelnice.org i pozwól, żeby nasz kalendarz rezerwacji pracował za Ciebie.</p>
                <button class="ssz-btn ssz-btn-primary" type="button" data-ssz-form-toggle="ssz-form-cta">
                    Zarejestruj strzelnicę teraz
                </button>

                <form
                    id="ssz-form-cta"
                    class="ssz-inline-form ssz-inline-form-centered"
                    method="POST"
                    action="{{ route('shooting-range-zone.send') }}"
                    style="{{ old('source') === 'cta' ? '' : 'display:none;' }}"
                >
                    @csrf
                    <input type="hidden" name="source" value="cta">
                    <input type="text" name="name" value="{{ old('source') === 'cta' ? old('name') : '' }}" placeholder="Imię i nazwisko *" required class="ssz-field @error('name') ssz-error @enderror">
                    <input type="email" name="email" value="{{ old('source') === 'cta' ? old('email') : '' }}" placeholder="E-mail *" required class="ssz-field @error('email') ssz-error @enderror">
                    <input type="tel" name="phone" value="{{ old('source') === 'cta' ? old('phone') : '' }}" placeholder="Telefon" class="ssz-field @error('phone') ssz-error @enderror">
                    <input type="text" name="shooting_range_name" value="{{ old('source') === 'cta' ? old('shooting_range_name') : '' }}" placeholder="Nazwa strzelnicy *" required class="ssz-field @error('shooting_range_name') ssz-error @enderror">
                    <input type="text" name="city" value="{{ old('source') === 'cta' ? old('city') : '' }}" placeholder="Miasto" class="ssz-field @error('city') ssz-error @enderror">
                    <textarea name="message" rows="3" placeholder="Wiadomość" class="ssz-field @error('message') ssz-error @enderror">{{ old('source') === 'cta' ? old('message') : '' }}</textarea>
                    <button class="ssz-btn ssz-btn-primary" type="submit">Wyślij zgłoszenie</button>
                </form>

                @if ($errors->any() && old('source') === 'cta')
                    <div class="ssz-form-errors">
                        Popraw błędy w formularzu i spróbuj ponownie.
                    </div>
                @endif
            </div>
        </section>
    </main>

    <style>
        .ssz-main {
            --ssz-bg: #000;
            --ssz-bg-alt: #0d0d0d;
            --ssz-line: #2b3036;
            --ssz-red: #ff3131;
            --ssz-red-bright: #ff5c5c;
            --ssz-text: #ecedee;
            --ssz-muted: #9aa0a7;
            --ssz-dim: #626971;
            background: var(--ssz-bg);
            color: var(--ssz-text);
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif;
            line-height: 1.6;
        }
        .ssz-main * { box-sizing: border-box; }
        .ssz-main a { color: inherit; text-decoration: none; }
        .ssz-main p, .ssz-main h1, .ssz-main h2, .ssz-main h3 { margin: 0; }
        .ssz-wrap { max-width: 1180px; margin: 0 auto; padding: 0 32px; }
        .ssz-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-height: 48px;
            padding: 13px 26px;
            border: 1px solid transparent;
            border-radius: 2px;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 1.2px;
            text-transform: uppercase;
            cursor: pointer;
            transition: transform .15s ease, background .15s ease, border-color .15s ease;
        }
        .ssz-btn:hover { transform: translateY(-1px); }
        .ssz-btn-primary { background: var(--ssz-red); color: #111; }
        .ssz-btn-primary:hover { background: var(--ssz-red-bright); color: #111; }
        .ssz-btn-ghost { background: transparent; color: var(--ssz-text); border-color: var(--ssz-line); }
        .ssz-btn-ghost:hover { border-color: var(--ssz-red); }
        .ssz-hero {
            position: relative;
            overflow: hidden;
            padding: 118px 0 96px;
            border-bottom: 1px solid var(--ssz-line);
        }
        .ssz-hero-grid {
            position: absolute;
            inset: 0;
            background-image:
                linear-gradient(rgba(255,49,49,.06) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,49,49,.06) 1px, transparent 1px);
            background-size: 64px 64px;
            mask-image: linear-gradient(to bottom, transparent, black 15%, black 70%, transparent);
        }
        .ssz-hero-rings {
            position: absolute;
            right: -160px;
            top: 50%;
            width: 780px;
            height: 780px;
            transform: translateY(-50%);
            opacity: .55;
            pointer-events: none;
        }
        .ssz-hero-rings svg { width: 100%; height: 100%; }
        .ssz-hero-content { position: relative; z-index: 2; max-width: 650px; }
        .ssz-eyebrow {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 22px;
            color: var(--ssz-red);
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 3px;
            text-transform: uppercase;
        }
        .ssz-eyebrow::before { content: ""; width: 28px; height: 1px; background: var(--ssz-red); }
        .ssz-hero h1 {
            margin-bottom: 26px;
            font-size: clamp(38px, 5.4vw, 62px);
            line-height: 1.04;
            font-weight: 800;
            letter-spacing: 0;
            text-transform: uppercase;
        }
        .ssz-hero h1 em { color: var(--ssz-red); font-style: normal; }
        .ssz-lede { max-width: 540px; margin-bottom: 38px !important; color: var(--ssz-muted); font-size: 18px; }
        .ssz-hero-actions { display: flex; flex-wrap: wrap; gap: 16px; align-items: center; margin-bottom: 42px; }
        .ssz-hero-stats { display: flex; flex-wrap: wrap; gap: 44px; margin-top: 34px; }
        .ssz-stat-num { color: var(--ssz-text); font-size: 30px; font-weight: 700; }
        .ssz-stat-label { margin-top: 4px; color: var(--ssz-dim); font-size: 11px; letter-spacing: 1.5px; text-transform: uppercase; }
        .ssz-main section { padding: 94px 0; border-bottom: 1px solid var(--ssz-line); }
        .ssz-section-head { max-width: 640px; margin-bottom: 60px; }
        .ssz-section-head h2 { font-size: clamp(28px, 3.4vw, 40px); line-height: 1.15; font-weight: 700; }
        .ssz-section-head p { max-width: 560px; margin-top: 18px; color: var(--ssz-muted); font-size: 16px; }
        .ssz-steps { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1px; background: var(--ssz-line); border: 1px solid var(--ssz-line); }
        .ssz-step { background: var(--ssz-bg); padding: 40px 34px; }
        .ssz-step-index { margin-bottom: 22px; color: var(--ssz-red); font-size: 13px; letter-spacing: 1px; text-transform: uppercase; }
        .ssz-step h3, .ssz-feature h3, .ssz-marketing-card h3, .ssz-offer-left h3 { margin-bottom: 12px; font-size: 18px; font-weight: 700; }
        .ssz-step p, .ssz-feature p, .ssz-marketing-card p { color: var(--ssz-muted); font-size: 14.5px; }
        .ssz-features { display: grid; grid-template-columns: repeat(3, 1fr); gap: 28px; }
        .ssz-feature { min-height: 210px; padding: 30px 26px; border: 1px solid var(--ssz-line); background: var(--ssz-bg-alt); border-radius: 2px; }
        .ssz-feature-icon { display: block; margin-bottom: 20px; color: var(--ssz-red); font-size: 34px; line-height: 1; }
        .ssz-marketing, .ssz-offer { background: var(--ssz-bg-alt); }
        .ssz-marketing-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 28px; }
        .ssz-marketing-card { padding: 34px 30px; border: 1px solid var(--ssz-line); background: var(--ssz-bg); border-radius: 2px; }
        .ssz-marketing-tag { display: inline-block; margin-bottom: 16px; color: var(--ssz-red); font-size: 11px; letter-spacing: 1.5px; text-transform: uppercase; }
        .ssz-marketing-note { margin-top: 28px !important; color: var(--ssz-dim); font-size: 13px; text-align: center; }
        .ssz-offer-grid { display: grid; grid-template-columns: 1.1fr .9fr; border: 1px solid var(--ssz-line); background: var(--ssz-bg); }
        .ssz-offer-left { padding: 54px 48px; border-right: 1px solid var(--ssz-line); }
        .ssz-offer-list { margin: 28px 0 0; padding: 0; list-style: none; }
        .ssz-offer-list li { display: flex; gap: 14px; padding: 13px 0; border-top: 1px solid var(--ssz-line); color: var(--ssz-text); font-size: 14.5px; }
        .ssz-offer-list span { color: var(--ssz-red); flex-shrink: 0; }
        .ssz-offer-right {
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 14px;
            padding: 54px 48px;
            background: linear-gradient(160deg, rgba(255,49,49,.08), transparent 60%);
        }
        .ssz-price-tag { color: var(--ssz-dim); font-size: 12px; letter-spacing: 2px; text-transform: uppercase; }
        .ssz-price { color: var(--ssz-text); font-size: 46px; font-weight: 800; line-height: 1.1; }
        .ssz-price span { color: var(--ssz-muted); font-size: 16px; font-weight: 400; }
        .ssz-price-sub, .ssz-offer-fine { color: var(--ssz-muted); font-size: 13.5px; }
        .ssz-faq { max-width: 760px; }
        .ssz-faq-item { border-bottom: 1px solid var(--ssz-line); }
        .ssz-faq-q {
            display: flex;
            width: 100%;
            align-items: center;
            justify-content: space-between;
            padding: 24px 0;
            border: 0;
            background: transparent;
            color: var(--ssz-text);
            font: inherit;
            font-size: 16px;
            font-weight: 600;
            text-align: left;
            cursor: pointer;
        }
        .ssz-faq-q span { color: var(--ssz-red); font-size: 20px; transition: transform .2s ease; }
        .ssz-faq-item.ssz-open .ssz-faq-q span { transform: rotate(45deg); }
        .ssz-faq-a { max-height: 0; overflow: hidden; transition: max-height .25s ease; }
        .ssz-faq-a p { max-width: 620px; padding-bottom: 24px; color: var(--ssz-muted); font-size: 14.5px; }
        .ssz-final-cta { position: relative; overflow: hidden; border-bottom: 0 !important; text-align: center; }
        .ssz-final-cta .ssz-hero-grid { mask-image: radial-gradient(circle at center, black, transparent 70%); }
        .ssz-final-cta-content { position: relative; z-index: 2; }
        .ssz-final-cta h2 { margin-bottom: 18px; font-size: clamp(28px, 4vw, 42px); font-weight: 600; line-height: 1.16; }
        .ssz-final-cta p { max-width: 520px; margin: 0 auto 38px; color: var(--ssz-muted); font-size: 16px; }
        .ssz-inline-form {
            display: flex;
            flex-direction: column;
            gap: 10px;
            width: 100%;
            max-width: 430px;
            margin-top: 20px;
            min-width: 0;
        }
        .ssz-inline-form-centered { max-width: 420px; margin: 24px auto 0; text-align: left; }
        .ssz-field {
            width: 100%;
            max-width: 100%;
            min-width: 0;
            padding: 12px 16px;
            border: 1px solid var(--ssz-line);
            border-radius: 2px;
            outline: none;
            background: #111;
            color: var(--ssz-text);
            font: inherit;
            font-size: 14px;
            transition: border-color .15s ease;
        }
        textarea.ssz-field { resize: vertical; }
        .ssz-field:focus { border-color: var(--ssz-red); }
        .ssz-field::placeholder { color: var(--ssz-dim); }
        .ssz-error { border-color: #ff5c5c; }
        .ssz-form-errors { margin-top: 12px; color: #ffb3b3; font-size: 14px; }
        @media (max-width: 860px) {
            .ssz-steps, .ssz-offer-grid { grid-template-columns: 1fr; }
            .ssz-features { grid-template-columns: repeat(2, 1fr); }
            .ssz-offer-left { border-right: 0; border-bottom: 1px solid var(--ssz-line); }
        }
        @media (max-width: 640px) {
            .ssz-wrap { padding: 0 20px; }
            .ssz-hero { padding: 86px 0 72px; }
            .ssz-main section { padding: 72px 0; }
            .ssz-features, .ssz-marketing-grid { grid-template-columns: 1fr; }
            .ssz-offer-left, .ssz-offer-right { padding: 34px 24px; }
            .ssz-hero-actions { align-items: stretch; }
            .ssz-btn { width: 100%; white-space: normal; text-align: center; }
            .ssz-inline-form { max-width: none; }
            .ssz-inline-form .ssz-btn { min-height: 46px; }
            .ssz-final-cta-content { max-width: 100%; }
            .ssz-hero-rings { right: -420px; opacity: .35; }
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('[data-ssz-form-toggle]').forEach(function (button) {
                button.addEventListener('click', function () {
                    const form = document.getElementById(button.dataset.sszFormToggle);
                    if (!form) return;

                    const isHidden = form.style.display === 'none';
                    form.style.display = isHidden ? 'flex' : 'none';

                    if (isHidden) {
                        form.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    }
                });
            });

            document.querySelectorAll('.ssz-faq-q').forEach(function (button) {
                button.addEventListener('click', function () {
                    const item = button.closest('.ssz-faq-item');
                    const answer = item.querySelector('.ssz-faq-a');
                    const isOpen = item.classList.contains('ssz-open');

                    document.querySelectorAll('.ssz-faq-item.ssz-open').forEach(function (openItem) {
                        openItem.classList.remove('ssz-open');
                        openItem.querySelector('.ssz-faq-a').style.maxHeight = null;
                    });

                    if (!isOpen) {
                        item.classList.add('ssz-open');
                        answer.style.maxHeight = answer.scrollHeight + 'px';
                    }
                });
            });
        });
    </script>
</x-app-layout>
