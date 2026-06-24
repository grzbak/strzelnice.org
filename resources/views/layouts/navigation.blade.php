{{-- MOBILE USER BAR --}}
@auth
    <div class="shadow-sm mobile-user-bar">
        <div class="gap-2 d-flex align-items-center">
            <img src="{{ auth()->user()->avatar_url }}" class="avatar">
            <div class="small">
                <div class="fw-semibold">{{ auth()->user()->name }}</div>
                <div class="text-muted">{{ auth()->user()->email }}</div>
            </div>
        </div>

        <div class="gap-3 d-flex fs-5 align-items-center">

            {{-- WIADOMOŚCI --}}
            <!--div class="nav-item dropdown">
                <a href="#" class="nav-link dropdown-toggle position-relative" data-bs-toggle="dropdown">
                    <i class="bi bi-chat-dots"></i>

                    @if (auth()->user()->unreadMessagesCount() > 0)
                        <span class="top-0 position-absolute start-100 translate-middle badge rounded-pill bg-danger">
                            {{ auth()->user()->unreadMessagesCount() }}
                        </span>
                    @endif
                </a>

                <ul class="p-2 dropdown-menu dropdown-menu-end" style="width:280px">
                    @forelse(auth()->user()->unreadMessages()->latest()->limit(5)->get() as $msg)
                        <li>
                            <a class="dropdown-item small" href="#">
                                <strong>{{ $msg->sender->name }}</strong>
                                <div class="text-muted text-truncate">
                                    {{ $msg->content }}
                                </div>
                            </a>
                        </li>
                    @empty
                        <li class="dropdown-item text-muted small">
                            Brak nowych wiadomości
                        </li>
                    @endforelse
                </ul>
            </!--div-->

            {{-- USTAWIENIA --}}
            <div class="dropdown">
                <a href="#" data-bs-toggle="dropdown">
                    <i class="bi bi-gear"></i>
                </a>

                <ul class="dropdown-menu dropdown-menu-end">
                    <li>
                        <a class="dropdown-item" href="{{ route('profile.edit') }}">
                            Edytuj profil
                        </a>
                    </li>
                    <li>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="dropdown-item text-danger">
                                Wyloguj
                            </button>
                        </form>
                    </li>
                </ul>
            </div>

        </div>
    </div>
@endauth


{{-- NAVBAR --}}
<nav class="shadow-sm bg-dark navbar navbar-expand-lg navbar-dark">
    <div class="container">

        <a class="navbar-brand fw-bold" href="{{ url('/') }}">
            <x-application-logo style="height: 50px;" />
        </a>

        <button class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#navbarMain">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarMain">

            {{-- MENU --}}
            <ul class="mx-auto navbar-nav">
                @auth
                    @if (auth()->user()->isSuperAdmin())
                        <li class="nav-item"><a class="nav-link" href="{{ route('admin.shootings') }}">Strzelnice</a></li>
                        <li class="nav-item"><a class="nav-link" href="{{ route('admin.users') }}">Użytkownicy</a></li>
                        <li class="nav-item"><a class="nav-link" href="{{ route('admin.packages.index') }}">Cenniki</a></li>
                        <li class="nav-item"><a class="nav-link" href="#">Strony</a></li>
                    @elseif(auth()->user()->isAdmin())
                         @php
                            $navigationShootingRange = auth()->user()
                                ->shootingRanges()
                                ->wherePivot('is_approved', true)
                                ->first();
                        @endphp

                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="{{ route('admin.shootings') }}"
                                id="shootingsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                Strzelnica
                            </a>

                            <ul class="dropdown-menu" aria-labelledby="shootingsDropdown">
                                <li>
                                    <a class="dropdown-item" href="{{ route('shooting.edit') }}">
                                        Dane
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="{{ route('shooting.lanes.edit') }}">
                                        Osie
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="{{ route('shooting.time.edit') }}">
                                        Godziny otwarcia
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="{{ route('admin.reservations.calendar') }}">
                                        Rezerwacje
                                    </a>
                                </li>
                            @if ($navigationShootingRange)
                                <li>
                                    <a
                                        class="dropdown-item"
                                        href="{{ route('shooting.gallery.index', $navigationShootingRange) }}"
                                    >
                                        Galeria
                                    </a>
                                </li>
                            @endif
                                <li>
                                    <a
                                        class="dropdown-item"
                                        href="{{ route('shooting.invitations.index') }}"
                                    >
                                        Użytkownicy
                                    </a>
                                </li>
                            </ul>
                        </li>
                    @endif
                @endauth

                <li class="nav-item"><a class="nav-link" href="#">O nas</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('maps') }}">Mapa</a></li>
                <li class="nav-item"><a class="nav-link" href="#">Strefa strzelnic</a></li>
                <li class="nav-item"><a class="nav-link" href="#">Kontakt</a></li>
            </ul>

            {{-- PRAWA STRONA --}}
            <div class="gap-3 d-flex align-items-center">

                @guest
                    <a href="{{ route('login') }}" class="btn btn-outline-danger btn-sm">
                        Logowanie
                    </a>
                    <a href="{{ route('register') }}" class="btn btn-danger btn-sm">
                        Rejestracja
                    </a>
                @endguest

                @auth
                    {{-- WIADOMOŚCI --}}
                    <div class="dropdown d-none d-lg-block">
                        <a href="#" class="position-relative fs-5" data-bs-toggle="dropdown">
                            <i class="bi bi-chat-dots"></i>

                            @if (auth()->user()->unreadMessagesCount() > 0)
                                <span
                                    class="top-0 position-absolute start-100 translate-middle badge rounded-pill bg-danger">
                                    {{ auth()->user()->unreadMessagesCount() }}
                                </span>
                            @endif
                        </a>

                        <ul class="p-2 dropdown-menu dropdown-menu-end" style="width:300px">
                            @foreach (auth()->user()->unreadMessages()->latest()->limit(5)->get() as $msg)
                                <li>
                                    <a class="dropdown-item small" href="#">
                                        <strong>{{ $msg->sender->name }}</strong>
                                        <div class="text-muted text-truncate">
                                            {{ $msg->content }}
                                        </div>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>

                    {{-- USER --}}
                    <div class="dropdown d-none d-lg-block">
                        <a href="#" class="gap-2 d-flex align-items-center dropdown-toggle text-decoration-none"
                            data-bs-toggle="dropdown">
                            <img src="{{ auth()->user()->avatar_url }}" class="avatar">
                            <span class="fw-semibold">{{ auth()->user()->name }}@if (auth()->user()->isSuperAdmin())
                                    (SA)
                                @endif
                            </span>
                        </a>

                        <ul class="dropdown-menu dropdown-menu-end">
                            <li class="px-3 py-2 small text-muted">
                                {{ auth()->user()->email }}
                            </li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li>
                                <a class="dropdown-item" href="{{ route('profile.edit') }}">
                                    Edytuj profil
                                </a>
                            </li>
                            <li>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button class="dropdown-item text-danger">
                                        Wyloguj
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </div>
                @endauth

            </div>
        </div>
    </div>
</nav>
