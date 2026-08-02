<x-app-layout>
    @include('components.hero')
    @include('components.latest-shooting-ranges', ['shootingRanges' => $latestShootingRanges])
    @include('components.how-it-works')
    @include('components.shooting-ranges-by-city')
</x-app-layout>
