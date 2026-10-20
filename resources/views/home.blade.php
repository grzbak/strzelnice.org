<x-app-layout :seo="[
    'title' => 'Strzelnice.org - wyszukiwarka i rezerwacja strzelnic online',
    'description' => 'Znajdź strzelnicę w swojej okolicy, sprawdź dostępne osie i zarezerwuj termin online.',
    'keywords' => 'strzelnice, rezerwacja strzelnicy, strzelnica online, osie strzeleckie',
    'canonical_url' => url('/'),
]">
    @include('components.hero')
    @include('components.latest-shooting-ranges', ['shootingRanges' => $latestShootingRanges])
    @include('components.how-it-works')
    @include('components.shooting-ranges-by-city')
</x-app-layout>
