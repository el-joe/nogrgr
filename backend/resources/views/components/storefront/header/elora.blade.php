{{-- Elora navbar (Figma 1:20867): white bar 90 — menu, logo, search pill, language, favourite, cart, account. --}}
@props(['mobileTitle' => null, 'back' => null])
<header data-sticky-header class="hidden bg-white lg:block">
    <div class="mx-auto flex h-[90px] max-w-[1440px] items-center gap-6 px-12">
        <x-sf::logo tone="dark" size="lg" data-header-shrink />
        <x-sf::header.search variant="elora" class="flex-1" />
        <div class="ms-auto flex items-center gap-8">
            <x-sf::lang-switch />
            @if (Route::has('storefront.favorites'))
                <a href="{{ route('storefront.favorites') }}" class="inline-flex items-center gap-2 text-base text-sf-ink"><x-ui::icon name="star" :size="24" />{{ __st('shell.favorite') }}</a>
            @endif
            <x-sf::header.cart />
            <x-sf::account-link />
        </div>
    </div>
</header>
<div class="lg:hidden">
    @if ($mobileTitle)
        <x-sf::mobile-bar :title="$mobileTitle" :back="$back" />
    @else
        <div class="flex h-14 items-center gap-3 border-b border-sf-border bg-white px-4">
            <x-sf::logo size="sm" />
            <div class="ms-auto flex items-center gap-4"><x-sf::lang-switch compact /><x-sf::account-link compact /></div>
        </div>
    @endif
</div>
