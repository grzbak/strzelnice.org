<x-app-layout>
    @php
        //dd($page->background)
    @endphp

    @if ($page->background)
        <div class="page-bg" style="background-image: url('{{ asset($page->background) }}');"></div>
    @else
        <div class="page-bg-2"></div>
    @endif

    <div class="cms-page-shell">
        @if ($page->baner)
            <section class="cms-page-banner" style="background-image: url('{{ asset($page->baner) }}');">
                <div class="cms-page-banner-shade">
                    <div class="cms-page-banner-title">
                        <h1>{{ $page->display_name }}</h1>
                    </div>
                </div>
            </section>
        @endif

        <main class="cms-page-main">
            <div class="px-0 container-fluid">
                <div class="row g-0 justify-content-center">
                    <div class="shadow-lg col-10 cms-page-content">
                        {!! $page->content !!}
                    </div>
                </div>
            </div>
        </main>
    </div>

    <style>
        .cms-page-shell {
            min-height: calc(100vh - 160px);
        }

        .cms-page-shell-has-background {
            background-position: center;
            background-size: cover;
            background-attachment: fixed;
            background-repeat: no-repeat;
        }

        .cms-page-banner {
            min-height: min(76vh, 300px);
            background-position: center;
            background-size: cover;
            background-repeat: no-repeat;
        }

        .cms-page-banner-shade {
            min-height: min(76vh, 300px);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            text-align: center;
            color: #fff;
            background: rgba(0, 0, 0, .34);
        }

        .cms-page-banner-title {
            width: min(980px, 100%);
            padding: 2rem 1rem;
        }

        .cms-page-banner-title h1 {
            margin: 0;
            font-size: clamp(2.6rem, 7vw, 6rem);
            font-weight: 700;
            line-height: 1.05;
            letter-spacing: 0;
            text-shadow: 0 5px 28px rgba(0, 0, 0, .55);
        }

        .cms-page-main {
            padding: 3rem 0;
        }

        .cms-page-banner + .cms-page-main {
            padding-top: 0;
            margin-top: -5rem;
            position: relative;
            z-index: 2;
        }

        .cms-page-content {
            padding: clamp(1.5rem, 4vw, 3rem);
            background: rgba(255, 255, 255, .7);
            backdrop-filter: blur(2px);
            -webkit-backdrop-filter: blur(2px);
        }

        .cms-page-content img {
            max-width: 100%;
            height: auto;
        }

        @media (max-width: 767.98px) {
            .cms-page-banner,
            .cms-page-banner-shade {
                min-height: 420px;
            }

            .cms-page-main .col-10 {
                width: calc(100% - 1.5rem);
            }

            .cms-page-banner + .cms-page-main {
                margin-top: -2.5rem;
            }
        }
    </style>
</x-app-layout>
