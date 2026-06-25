<x-app-layout>
    <div
        class="cms-page-shell"
        @if ($page->background)
            style="background-image: url('{{ asset($page->background) }}');"
        @endif
    >
        @if ($page->baner)
            <section class="cms-page-hero" style="background-image: url('{{ asset($page->baner) }}');">
                <div class="cms-page-hero-overlay">
                    <h1>{{ $page->display_name }}</h1>
                </div>
            </section>
        @endif

        <main class="container py-4">
            <div class="col-10 p-4 mx-auto cms-page-content shadow-sm">
                {!! $page->content !!}
            </div>
        </main>
    </div>

    <style>
        .cms-page-shell {
            min-height: calc(100vh - 160px);
            background-position: center;
            background-size: cover;
            background-attachment: fixed;
            background-repeat: no-repeat;
        }

        .cms-page-hero {
            min-height: 360px;
            background-position: center;
            background-size: cover;
            background-repeat: no-repeat;
        }

        .cms-page-hero-overlay {
            min-height: 360px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            text-align: center;
            color: #fff;
            background: rgba(0, 0, 0, .35);
        }

        .cms-page-hero-overlay h1 {
            margin: 0;
            font-size: clamp(2rem, 5vw, 4.5rem);
            font-weight: 700;
            letter-spacing: 0;
            text-shadow: 0 2px 18px rgba(0, 0, 0, .45);
        }

        .cms-page-content {
            background: rgba(255, 255, 255, .7);
            backdrop-filter: blur(2px);
        }

        .cms-page-content img {
            max-width: 100%;
            height: auto;
        }
    </style>
</x-app-layout>