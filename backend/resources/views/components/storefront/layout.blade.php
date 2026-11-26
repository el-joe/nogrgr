{{--
    Storefront page shell (x-sf::layout) shared by every storefront page: theme preview bar
    (vendor preview session only), SEO head + store appearance stylesheet / fonts, store bar
    (promo + header links, VEN-034), family header, main slot, footer, mobile bottom nav, toast region, back-to-top, and the
    StorefrontContext JSON island (#sf-context) the storefront JS reads, and the tracking
    consent notice + #sf-tracking island when the store has pixels on (VEN-083).
    <x-sf::layout :title="…" :seo="$seo" mobile-title="My profile" :back="…" nav="account"> … </x-sf::layout>
--}}
@props(['title' => null, 'family' => null, 'mobileTitle' => null, 'back' => null, 'nav' => null, 'page' => null, 'seo' => null])
@php
    $family ??= \App\Modules\Storefront\Support\StorefrontTheme::family();
    $context = app(\App\Modules\Storefront\Services\StorefrontContext::class);
    $store = \App\Modules\Storefront\Support\StorefrontTheme::storeName();
    $fullTitle = $seo?->title ?? ($title ? $title.' | '.$store : $store);
    $appearance = app(\App\Modules\Storefront\Services\Appearance\StorefrontAppearance::class);
    $appearanceCss = $appearance->stylesheetUrl($family);
    $storeFonts = $appearance->fontEntries();
    $tracking = app(\App\Modules\Storefront\Services\Tracking\TrackingPixelService::class)->storefrontConfig();
    $island = $context->toClient() + [
        'routes' => array_filter([
            'cart_items' => Route::has('store-api.v1.cart.items.store') ? route('store-api.v1.cart.items.store') : null,
            'cart' => Route::has('storefront.cart') ? route('storefront.cart') : null,
            'autocomplete' => Route::has('storefront.search.autocomplete') ? route('storefront.search.autocomplete') : null,
            'search' => Route::has('storefront.search') ? route('storefront.search') : null,
            'favorite_toggle' => Route::has('storefront.favorites.toggle') ? route('storefront.favorites.toggle') : null,
            'login' => Route::has('storefront.account.login') ? route('storefront.account.login') : null,
        ]),
    ];
@endphp
<x-layouts.storefront :family="$family" :title="$title" :full-title="$fullTitle">
    <x-slot:head>
        <x-sf::seo :seo="$seo" />
        {{-- Store appearance (VEN-034b/c, decision #91): chosen font faces + the token stylesheet. --}}
        @if ($storeFonts !== [])
            @vite($storeFonts)
        @endif
        @if ($appearanceCss)
            <link rel="stylesheet" href="{{ $appearanceCss }}">
        @endif
    </x-slot:head>
    <script type="application/json" id="sf-context">{!! json_encode($island, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
    <x-sf::preview-bar />
    <div class="sf-page flex min-h-screen flex-col" data-theme-variant="{{ $context->theme()->variant }}" @if ($page) data-sf-page="{{ $page }}" @endif>
        <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:start-3 focus:top-3 focus:z-50 focus:rounded-full focus:bg-white focus:px-4 focus:py-2 focus:text-sm focus:shadow">{{ __st('shell.skip_to_content') }}</a>
        <x-sf::store-bar />
        <x-sf::header :family="$family" :mobile-title="$mobileTitle" :back="$back" />
        <main id="main" {{ $attributes->class(['flex-1']) }}>
            {{ $slot }}
        </main>
        @if (\App\Modules\Ai\Services\Tools\OpenAiTools::storefrontImageSearch())
            <x-sf::image-search :action="route('storefront.search.image')" />
        @endif
        <x-sf::footer :family="$family" />
        <x-sf::mobile-nav :family="$family" :active="$nav" />
    </div>
    @if ($family === 'ecommet')
        <x-sf::back-to-top />
    @endif
    @if ($tracking)
        <x-sf::cookie-consent :config="$tracking" />
    @endif
    <x-ui::toasts />
</x-layouts.storefront>
