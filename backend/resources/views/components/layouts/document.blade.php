{{--
    Root HTML document shared by every surface. Do not use directly; use
    <x-layouts.panel>, <x-layouts.central> or <x-layouts.storefront>.
    No inline <script>/<style>: everything goes through Vite entries.
--}}
@props([
    'entries' => [],
    'title' => null,
    'surface' => 'central',
    'bodyClass' => '',
    'fullTitle' => null,
])
<!DOCTYPE html>
<html
    lang="{{ \App\Shared\Support\Locale::htmlLang() }}"
    dir="{{ \App\Shared\Support\Locale::direction() }}"
    data-surface="{{ $surface }}"
    data-country="{{ \App\Shared\Support\Locale::visitorCountry() }}"
    {{-- Storefront themes are light-only designs: the panels' dark cookie must not reach them. --}}
    @class(['dark' => $surface !== 'storefront' && request()->cookie('theme') === 'dark'])
>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $fullTitle ?? ($title ? $title.' · ' : '').config('app.name') }}</title>
    @php($fontPreload = \App\Shared\Support\FontPreload::url($surface, \App\Shared\Support\Locale::direction() === 'rtl'))
    @if ($fontPreload)
        <link rel="preload" href="{{ $fontPreload }}" as="font" type="font/woff2" crossorigin>
    @endif
    {{ $head ?? '' }}
    <script type="application/json" id="app-i18n">@json(trans('js'))</script>
    @vite($entries)
</head>
<body {{ $attributes->class(['min-h-screen antialiased', $bodyClass]) }}>
    {{ $slot }}
</body>
</html>
