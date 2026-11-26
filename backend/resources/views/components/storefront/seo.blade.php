{{-- Head tags of a storefront page from App\Modules\Storefront\DTOs\SeoData (null = description-less defaults). --}}
@props(['seo' => null])
@if ($seo)
    @if ($seo->description)
        <meta name="description" content="{{ $seo->description }}">
    @endif
    <meta name="robots" content="{{ $seo->index ? 'index, follow, max-image-preview:large' : 'noindex, follow' }}">
    <link rel="canonical" href="{{ $seo->canonical }}">
    @foreach ($seo->alternates as $lang => $href)
        <link rel="alternate" hreflang="{{ $lang }}" href="{{ $href }}">
    @endforeach
    <meta property="og:type" content="{{ $seo->type }}">
    <meta property="og:site_name" content="{{ $seo->siteName }}">
    <meta property="og:title" content="{{ $seo->title }}">
    @if ($seo->description)
        <meta property="og:description" content="{{ $seo->description }}">
    @endif
    <meta property="og:url" content="{{ $seo->canonical }}">
    <meta property="og:locale" content="{{ $seo->locale }}">
    @if ($seo->image)
        <meta property="og:image" content="{{ $seo->image }}">
    @endif
    <meta name="twitter:card" content="{{ $seo->image ? 'summary_large_image' : 'summary' }}">
    <meta name="twitter:title" content="{{ $seo->title }}">
    @if ($seo->description)
        <meta name="twitter:description" content="{{ $seo->description }}">
    @endif
    @if ($seo->image)
        <meta name="twitter:image" content="{{ $seo->image }}">
    @endif
    @foreach ($seo->jsonLd as $graph)
        <script type="application/ld+json">{!! json_encode($graph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
    @endforeach
@endif
