@php
    $cityColumns = [
        ['Białystok', 'Bydgoszcz', 'Gdańsk', 'Gorzów Wielkopolski', 'Katowice'],
        ['Kielce', 'Kraków', 'Lublin', 'Łódź', 'Olsztyn'],
        ['Opole', 'Poznań', 'Rzeszów', 'Szczecin'],
        ['Toruń', 'Warszawa', 'Wrocław', 'Zielona Góra'],
    ];
@endphp

<section class="shooting-cities" aria-labelledby="shooting-cities-title">
    <div class="container">
        <h4 id="shooting-cities-title" class="shooting-cities-title">
            Znajdź strzelnicę według miasta
        </h4>

        <div class="shooting-cities-grid">
            @foreach ($cityColumns as $columnIndex => $cities)
                <ul
                    class="shooting-cities-list"
                    style="--city-column-delay: {{ $columnIndex * 110 }}ms;"
                >
                    @foreach ($cities as $city)
                        <li>
                            <a
                                href="{{ url('/lista-strzelnic') }}?m={{ urlencode($city) }}"
                                class="shooting-city-link"
                            >
                                <i class="bi bi-chevron-right" aria-hidden="true"></i>
                                <span>{{ $city }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endforeach
        </div>
    </div>
</section>

<style>
    .shooting-cities {
        position: relative;
        overflow: hidden;
        padding: 72px 0 80px;
        background: linear-gradient(180deg, #f5f8f8 0%, #fff 100%);
        border-top: 1px solid #e9eeee;
        border-bottom: 1px solid #e9eeee;
    }

    .shooting-cities-title {
        max-width: 760px;
        margin: 0 auto 52px;
        color: #292d32;
        font-size: 2.25rem;
        font-weight: 700;
        line-height: 1.2;
        letter-spacing: 0;
        text-align: center;
    }

    .shooting-cities-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 24px 64px;
    }

    .shooting-cities-list {
        display: flex;
        flex-direction: column;
        gap: 18px;
        margin: 0;
        padding: 0;
        list-style: none;
        opacity: 0;
        transform: translateY(24px);
        transition:
            opacity 0.55s ease var(--city-column-delay),
            transform 0.55s ease var(--city-column-delay);
    }

    .shooting-cities.is-visible .shooting-cities-list {
        opacity: 1;
        transform: translateY(0);
    }

    .shooting-city-link {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        color: #202428;
        text-decoration: none;
        transition: color 0.18s ease;
    }

    .shooting-city-link i {
        flex: 0 0 auto;
        color: #2495a5;
        font-size: 0.9rem;
        transition: transform 0.18s ease;
    }

    .shooting-city-link:hover,
    .shooting-city-link:focus-visible {
        color: #167d8c;
    }

    .shooting-city-link:hover i,
    .shooting-city-link:focus-visible i {
        transform: translateX(3px);
    }

    @media (max-width: 991.98px) {
        .shooting-cities {
            padding: 56px 0 64px;
        }

        .shooting-cities-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 38px 48px;
        }
    }

    @media (max-width: 575.98px) {
        .shooting-cities {
            padding: 44px 0 52px;
        }

        .shooting-cities-title {
            max-width: 300px;
            margin-bottom: 36px;
            font-size: 1.75rem;
        }

        .shooting-cities-grid {
            grid-template-columns: 1fr;
            gap: 0;
        }

        .shooting-cities-list {
            gap: 0;
        }

        .shooting-city-link {
            width: 100%;
            padding: 12px 4px;
            border-bottom: 1px solid #e4ebeb;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .shooting-cities-list {
            opacity: 1;
            transform: none;
            transition: none;
        }
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const section = document.querySelector('.shooting-cities');

        if (!section) {
            return;
        }

        if (!('IntersectionObserver' in window)) {
            section.classList.add('is-visible');
            return;
        }

        const observer = new IntersectionObserver(entries => {
            entries.forEach(entry => {
                if (!entry.isIntersecting) {
                    return;
                }

                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            });
        }, {
            threshold: 0.18,
        });

        observer.observe(section);
    });
</script>
