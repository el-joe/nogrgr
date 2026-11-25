{{--
    Central website <head> metadata from App\Modules\Content\Support\SeoData: description,
    robots, canonical + hreflang alternates, Open Graph, Twitter card and JSON-LD islands.
--}}
@php
    /** @var \App\Modules\Content\Support\SeoData $seo */
    $ogLocale = ['ar' => 'ar_AR', 'en' => 'en_US'];
@endphp
<meta name="description" content="{{ $seo->description }}">
<meta name="robots" content="{{ $seo->noindex ? 'noindex, nofollow' : 'index, follow, max-image-preview:large' }}">
<link rel="canonical" href="{{ $seo->canonical }}">
@foreach ($seo->alternates as $hreflang => $url)
    <link rel="alternate" hreflang="{{ $hreflang }}" href="{{ $url }}">
@endforeach
<meta property="og:site_name" content="{{ config('app.name') }}">
<meta property="og:type" content="{{ $seo->type }}">
<meta property="og:title" content="{{ $seo->fullTitle() }}">
<meta property="og:description" content="{{ $seo->description }}">
<meta property="og:url" content="{{ $seo->canonical }}">
<meta property="og:locale" content="{{ $ogLocale[app()->getLocale()] ?? app()->getLocale() }}">
@if ($seo->image)
    <meta property="og:image" content="{{ $seo->image }}">
@endif
@if ($seo->publishedTime)
    <meta property="article:published_time" content="{{ $seo->publishedTime }}">
@endif
@if ($seo->modifiedTime)
    <meta property="article:modified_time" content="{{ $seo->modifiedTime }}">
@endif
<meta name="twitter:card" content="{{ $seo->image ? 'summary_large_image' : 'summary' }}">
<meta name="twitter:title" content="{{ $seo->fullTitle() }}">
<meta name="twitter:description" content="{{ $seo->description }}">
@if ($seo->image)
    <meta name="twitter:image" content="{{ $seo->image }}">
@endif
@foreach ($seo->jsonLd as $block)
    <script type="application/ld+json">{!! json_encode($block, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
@endforeach
