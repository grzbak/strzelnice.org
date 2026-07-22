@php
    $googleAnalyticsId = $activeSystemConfiguration?->google_analytics_id;
    $cookieSettings = json_decode((string) request()->cookie('cookie_settings'), true) ?: [];
    $analyticsConsent = ! empty($cookieSettings['analytics']) ? 'granted' : 'denied';
    $marketingConsent = ! empty($cookieSettings['marketing']) ? 'granted' : 'denied';
@endphp

@if ($googleAnalyticsId)
    <script>
        window.dataLayer = window.dataLayer || [];

        function gtag() {
            dataLayer.push(arguments);
        }

        gtag('consent', 'default', {
            ad_storage: @json($marketingConsent),
            analytics_storage: @json($analyticsConsent),
            ad_user_data: @json($marketingConsent),
            ad_personalization: @json($marketingConsent),
            wait_for_update: 500
        });
    </script>
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ $googleAnalyticsId }}"></script>
    <script>
        gtag('js', new Date());
        gtag('config', @json($googleAnalyticsId));
    </script>
@endif
