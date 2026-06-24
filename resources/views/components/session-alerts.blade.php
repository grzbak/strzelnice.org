@props([
    'timeout' => 3000,
])

@php
    $alerts = [];

    if (session('success')) {
        $alerts[] = [
            'class' => 'alert-success',
            'message' => session('success'),
        ];
    }

    if (session('error')) {
        $alerts[] = [
            'class' => 'alert-danger',
            'message' => session('error'),
        ];
    }

    $componentId = 'session-alerts-' . uniqid();
    $timeoutMs = max(0, (int) $timeout);
@endphp

@if ($alerts)
    <style>
        @keyframes session-alert-countdown {
            from { transform: scaleX(1); }
            to { transform: scaleX(0); }
        }

        .session-alert {
            transition: opacity 180ms ease, transform 180ms ease;
        }

        .session-alert.is-hiding {
            opacity: 0;
            transform: translateY(-6px);
        }

        .session-alert__progress {
            position: absolute;
            left: 0;
            bottom: 0;
            width: 100%;
            height: 3px;
            opacity: .55;
            transform-origin: left center;
            animation-name: session-alert-countdown;
            animation-timing-function: linear;
            animation-fill-mode: forwards;
        }
    </style>

    <div id="{{ $componentId }}" {{ $attributes->merge(['class' => 'mb-4']) }} data-session-alerts data-timeout="{{ $timeoutMs }}">
        @foreach ($alerts as $alert)
            <div class="alert {{ $alert['class'] }} alert-dismissible fade show session-alert position-relative overflow-hidden pe-5" role="alert" data-session-alert>
                <div>{{ $alert['message'] }}</div>

                <button type="button" class="btn-close" aria-label="Zamknij" data-session-alert-close></button>

                <div class="session-alert__progress" style="animation-duration: {{ $timeoutMs }}ms;"></div>
            </div>
        @endforeach
    </div>

    <script>
        (() => {
            const root = document.getElementById(@json($componentId));

            if (!root) {
                return;
            }

            const timeout = Number(root.dataset.timeout || 3000);

            const closeAlert = (alert) => {
                if (!alert || alert.dataset.closed === 'true') {
                    return;
                }

                alert.dataset.closed = 'true';
                alert.classList.add('is-hiding');

                window.setTimeout(() => {
                    if (window.bootstrap && window.bootstrap.Alert) {
                        window.bootstrap.Alert.getOrCreateInstance(alert).close();
                        return;
                    }

                    alert.remove();
                }, 180);
            };

            root.querySelectorAll('[data-session-alert]').forEach((alert) => {
                alert.querySelector('[data-session-alert-close]')?.addEventListener('click', () => {
                    closeAlert(alert);
                });

                if (timeout > 0) {
                    window.setTimeout(() => closeAlert(alert), timeout);
                }
            });
        })();
    </script>
@endif