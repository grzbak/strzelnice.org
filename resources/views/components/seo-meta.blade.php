@props(['seo' => null])

@php
    $configuration = \App\Models\SystemConfiguration::activeConfiguration();
    $data = collect($seo ?? [])
        ->filter(fn ($value) => filled($value))
        ->all();

    $title = $data['title']
        ?? $data['meta_title']
        ?? $configuration?->default_meta_title
        ?? config('app.name', 'Strzelnice');

    $description = $data['description']
        ?? $data['meta_description']
        ?? $configuration?->default_meta_description;

    $keywords = $data['keywords']
        ?? $data['meta_keywords']
        ?? $configuration?->default_meta_keywords;

    $ogTitle = $data['og_title']
        ?? $configuration?->default_og_title
        ?? $title;

    $ogDescription = $data['og_description']
        ?? $configuration?->default_og_description
        ?? $description;

    $ogImage = $data['og_image']
        ?? $configuration?->default_og_image;

    $canonicalUrl = $data['canonical_url'] ?? url()->current();
    $robots = $data['robots'] ?? $configuration?->default_robots ?? 'index,follow';

    $absoluteImage = null;

    if ($ogImage) {
        $absoluteImage = \Illuminate\Support\Str::startsWith($ogImage, ['http://', 'https://'])
            ? $ogImage
            : asset(ltrim($ogImage, '/'));
    }
@endphp

<title>{{ $title }}</title>

@if ($description)
    <meta name="description" content="{{ $description }}">
@endif

@if ($keywords)
    <meta name="keywords" content="{{ $keywords }}">
@endif

<meta name="robots" content="{{ $robots }}">
<link rel="canonical" href="{{ $canonicalUrl }}">

<meta property="og:title" content="{{ $ogTitle }}">

@if ($ogDescription)
    <meta property="og:description" content="{{ $ogDescription }}">
@endif

@if ($absoluteImage)
    <meta property="og:image" content="{{ $absoluteImage }}">
@endif

<meta property="og:type" content="{{ $data['og_type'] ?? 'website' }}">
<meta property="og:url" content="{{ $canonicalUrl }}">
<meta property="og:site_name" content="Strzelnice.org">
<meta property="og:locale" content="pl_PL">
<meta name="twitter:card" content="{{ $absoluteImage ? 'summary_large_image' : 'summary' }}">

{{-- Dane strukturalne schema.org (JSON-LD) --}}
@foreach (array_filter($data['schema'] ?? []) as $schema)
    <script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
@endforeach
