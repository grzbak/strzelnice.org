@props(['shootingRanges' => collect()])

@php
    $ranges = collect($shootingRanges);
    $sliderItems = $ranges;

    if ($ranges->count() > 3) {
        for ($i = 0; $i < 7; $i++) {
            $sliderItems = $sliderItems->concat($ranges);
        }
    }
@endphp

@if ($ranges->isNotEmpty())
    <section class="latest-ranges-section">
        <div class="container">
            <div class="latest-ranges-header">
                <div>
                    <h2>Ostatnio dodane</h2>
                </div>
                <a href="{{ route('shootings.list') }}">
                    Zobacz wszystkie
                    <i class="bi bi-arrow-right" aria-hidden="true"></i>
                </a>
            </div>

            <div class="latest-ranges-slider splide" data-latest-ranges-slider>
                <div class="latest-ranges-viewport splide__track overflow-visible! splide__track--loop splide__track--ltr splide__track--draggable">
                    <div class="latest-ranges-track splide__list">
                        @foreach ($sliderItems as $shootingRange)
                            <a
                                href="{{ $shootingRange->public_link }}"
                                class="latest-range-card splide__slide"
                                aria-label="Zobacz strzelnicę {{ $shootingRange->name }}"
                                draggable="false"
                            >
                                <span class="latest-range-logo">
                                    @if ($shootingRange->brand_url)
                                        <img
                                            src="{{ $shootingRange->brand_url }}"
                                            alt="Logo {{ $shootingRange->name }}"
                                            loading="lazy"
                                            draggable="false"
                                        >
                                    @else
                                        <span class="latest-range-fallback">
                                            <i class="bi bi-crosshair2" aria-hidden="true"></i>
                                            <span>{{ $shootingRange->name }}</span>
                                        </span>
                                    @endif
                                </span>
                                <span class="latest-range-hover-mask" aria-hidden="true">
                                    <i class="bi bi-search"></i>
                                </span>
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </section>

    <style>
        .latest-ranges-section {
            --latest-logo-height: clamp(46px, 6vw, 74px);
            --latest-logo-max-width: clamp(120px, 24vw, 260px);
            --latest-slider-height: var(--latest-logo-height);
            --latest-logo-gap: clamp(.75rem, 2vw, 1.15rem);
            padding: clamp(.45rem, 1.2vw, .75rem) 0;
            background: #fff;
            border-top: 1px solid #e6ebeb;
            border-bottom: 1px solid #e6ebeb;
            overflow-x: clip;
        }

        .latest-ranges-section > .container {
            display: block;
        }

        .latest-ranges-header {
            display: flex;
            align-items: end;
            flex-direction: row;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: clamp(.35rem, .9vw, .55rem);
        }

        .latest-ranges-header h2 {
            margin: 0;
            color: #b42318;
            font-size: clamp(.98rem, 2vw, 1.12rem);
            font-weight: 800;
        }

        .latest-ranges-header a {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            color: #b42318;
            font-size: clamp(.74rem, 1.6vw, .82rem);
            font-weight: 800;
            text-decoration: none;
            white-space: nowrap;
        }

        .latest-ranges-slider {
            width: 100vw;
            height: var(--latest-slider-height);
            margin-left: calc(50% - 50vw);
            overflow: visible;
            cursor: grab;
            touch-action: pan-y;
            user-select: none;
        }

        .latest-ranges-slider.is-dragging {
            cursor: grabbing;
        }

        .latest-ranges-viewport {
            overflow: visible;
        }

        .latest-ranges-track {
            display: flex;
            gap: var(--latest-logo-gap);
            width: max-content;
            min-width: 100%;
            will-change: transform;
        }

        .latest-range-card {
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            flex: 0 0 auto;
            width: auto;
            min-width: 0;
            max-width: none;
            height: var(--latest-logo-height);
            padding: 0 clamp(.2rem, .8vw, .45rem);
            color: #f7f8f8;
            text-decoration: none;
            overflow: hidden;
            -webkit-user-drag: none;
        }

        .latest-range-card:hover,
        .latest-range-card:focus-visible,
        .latest-range-card:focus-within {
            color: #fff;
        }

        .latest-range-hover-mask {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            position: absolute;
            inset: 0 .35rem;
            color: #b42318;
            background: rgb(255 255 255 / 72%);
            border-radius: 8px;
            opacity: 0;
            pointer-events: none;
            transition: opacity .18s ease;
        }

        .latest-range-hover-mask i {
            font-size: clamp(1.4rem, 3vw, 2rem);
            line-height: 1;
        }

        .latest-range-card:hover .latest-range-hover-mask,
        .latest-range-card:focus-visible .latest-range-hover-mask {
            opacity: 1;
        }

        .latest-range-logo {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 250px;
            max-width: var(--latest-logo-max-width);
            height: var(--latest-logo-height);
            overflow: hidden;
            color: #d5dadd;
            font-size: 2rem;
        }

        .latest-range-card .latest-range-logo img {
            display: block;
            width: auto !important;
            max-width: var(--latest-logo-max-width);
            height: var(--latest-logo-height) !important;
            max-height: none !important;
            object-fit: contain;
        }

        .latest-range-fallback {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            position: relative;
            width: min(var(--latest-logo-max-width), 220px);
            height: var(--latest-logo-height);
            padding: 0 .85rem;
            color: #5f676b;
            overflow: hidden;
            text-align: center;
        }

        .latest-range-fallback i {
            position: absolute;
            inset: 50% auto auto 50%;
            color: #d4d8da;
            font-size: calc(var(--latest-logo-height) * .95);
            line-height: 1;
            transform: translate(-50%, -50%);
        }

        .latest-range-fallback span {
            position: relative;
            z-index: 1;
            color: #384044;
            font-size: clamp(.68rem, 1.25vw, .86rem);
            font-weight: 900;
            line-height: 1.15;
            text-shadow: 0 1px 0 rgb(255 255 255 / 70%);
        }

        @media (max-width: 991.98px) {
            .latest-ranges-section {
                --latest-logo-max-width: clamp(115px, 30vw, 220px);
            }
        }

        @media (max-width: 575.98px) {
            .latest-ranges-section {
                --latest-logo-height: clamp(44px, 13vw, 58px);
                --latest-logo-max-width: min(58vw, 190px);
                --latest-logo-gap: .8rem;
                padding: .55rem 0;
            }

            .latest-ranges-header {
                align-items: stretch;
                flex-direction: column;
                gap: .35rem;
                margin-bottom: .6rem;
            }

            .latest-ranges-header a {
                width: fit-content;
            }
        }
    </style>

    <script>
        (() => {
            const slider = document.querySelector('[data-latest-ranges-slider]');

            if (! slider || slider.dataset.initialized === 'true') {
                return;
            }

            slider.dataset.initialized = 'true';

            const track = slider.querySelector('.latest-ranges-track');
            let offset = 0;
            let speed = 22;
            let loopWidth = 0;
            let gap = 16;
            let previousTimestamp = null;
            let isPaused = false;
            let isPointerDown = false;
            let isDragging = false;
            let hasDragged = false;
            let startX = 0;
            let startOffset = 0;
            const canLoop = @json($ranges->count() > 3);
            const uniqueCount = @json($ranges->count());

            function updateMetrics() {
                const styles = window.getComputedStyle(track);
                gap = Number.parseFloat(styles.columnGap || styles.gap) || 16;

                const slideWidths = Array.from(track.children)
                    .slice(0, uniqueCount)
                    .map(slide => slide.getBoundingClientRect().width);

                loopWidth = canLoop
                    ? slideWidths.reduce((total, width) => total + width, 0) + (gap * uniqueCount)
                    : track.scrollWidth;

                render();
            }

            function normalizeOffset() {
                if (! canLoop || loopWidth <= 0) {
                    offset = 0;
                    return;
                }

                while (offset <= -loopWidth) {
                    offset += loopWidth;
                }

                while (offset > 0) {
                    offset -= loopWidth;
                }
            }

            function render() {
                normalizeOffset();
                track.style.transform = `translate3d(${offset}px, 0, 0)`;
            }

            function tick(timestamp) {
                if (previousTimestamp === null) {
                    previousTimestamp = timestamp;
                }

                const delta = (timestamp - previousTimestamp) / 1000;
                previousTimestamp = timestamp;

                if (canLoop && ! isPaused && ! isDragging) {
                    offset -= speed * delta;
                    render();
                }

                window.requestAnimationFrame(tick);
            }

            slider.addEventListener('mouseenter', () => {
                isPaused = true;
            });

            slider.addEventListener('mouseleave', () => {
                if (! isPointerDown) {
                    isPaused = false;
                    isDragging = false;
                    slider.classList.remove('is-dragging');
                }
            });

            slider.addEventListener('pointerdown', event => {
                if (event.button !== 0) {
                    return;
                }

                isPointerDown = true;
                isDragging = false;
                isPaused = true;
                startX = event.clientX;
                startOffset = offset;
                hasDragged = false;
            });

            window.addEventListener('pointermove', event => {
                if (! isPointerDown) {
                    return;
                }

                const distance = event.clientX - startX;
                hasDragged = Math.abs(distance) > 6;

                if (! hasDragged) {
                    return;
                }

                isDragging = true;
                slider.classList.add('is-dragging');
                offset = startOffset + distance;
                render();
                event.preventDefault();
            });

            window.addEventListener('pointerup', event => {
                isPointerDown = false;
                isDragging = false;
                isPaused = false;
                slider.classList.remove('is-dragging');
            });

            slider.addEventListener('focusin', () => {
                isPaused = true;
            });

            slider.addEventListener('focusout', () => {
                isPaused = false;
            });

            slider.addEventListener('pointercancel', () => {
                isPointerDown = false;
                isDragging = false;
                isPaused = false;
                slider.classList.remove('is-dragging');
            });

            slider.querySelectorAll('a').forEach(link => {
                link.addEventListener('dragstart', event => {
                    event.preventDefault();
                });

                link.addEventListener('click', event => {
                    if (! hasDragged) {
                        return;
                    }

                    event.preventDefault();

                    window.setTimeout(() => {
                        hasDragged = false;
                    }, 0);
                });
            });

            track.querySelectorAll('img').forEach(image => {
                if (image.complete) {
                    return;
                }

                image.addEventListener('load', updateMetrics, { once: true });
                image.addEventListener('error', updateMetrics, { once: true });
            });

            window.addEventListener('resize', updateMetrics);

            updateMetrics();
            window.requestAnimationFrame(tick);
        })();
    </script>
@endif
