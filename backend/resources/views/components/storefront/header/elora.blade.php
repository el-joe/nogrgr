{{--
    Elora navbar (Figma 1:20866: Default 1:20867 / menu open 1:20989).
    Desktop: 90-tall white bar — menu button (opens the category mega menu: categories at the
    start, sub-categories in a grey column, incl. store-own categories), logo, search pill
    with autocomplete, flag · "En/ SAR" (preferences panel), Favorite, Cart (count badge in
    the variant accent, opens the mini cart), "Welcome / Sign in / Register".
    Phone home (m 1:21125): accent header — greeting + delivery place, notifications-free
    no bell (there is no customer notification page), white search pill. Other phone pages: compact logo bar, or the
    "‹ Title" app bar when $mobileTitle is set.
--}}
@props(['mobileTitle' => null, 'back' => null])
@php
    $menu = app(\App\Modules\Storefront\Services\Catalog\CategoryCatalog::class)->menu();
    $customer = auth('customer')->user();
    $context = app(\App\Modules\Storefront\Services\StorefrontContext::class);
    $home = request()->routeIs('storefront.home');
@endphp
<header data-sticky-header class="elo-header sticky top-0 z-40 bg-white max-lg:static">
    <div class="hidden lg:block">
        <div class="elo-header-bar mx-auto flex h-[90px] max-w-[1440px] items-center gap-4 px-6 xl:gap-6 xl:px-12" data-elo-menu>
            @if ($menu !== [])
                <button type="button" class="elo-menu-btn mo-press" aria-expanded="false" aria-controls="elo-mega" data-elo-menu-toggle data-label-open="{{ __st('elora.menu.open') }}" data-label-close="{{ __st('elora.menu.close') }}" aria-label="{{ __st('elora.menu.open') }}">
                    <span class="elo-menu-icon" aria-hidden="true"><span></span><span></span><span></span></span>
                    <span class="text-[10px] leading-none" data-elo-menu-label>{{ __st('elora.menu.label') }}</span>
                </button>
            @endif
            <x-sf::logo tone="dark" size="lg" data-header-shrink />
            <x-sf::header.search variant="elora" class="min-w-[180px] flex-1 xl:min-w-[220px]" />
            <div class="flex shrink-0 items-center gap-5 xl:gap-10">
                <x-sf::locale-button tone="dark" />
                @if (Route::has('storefront.favorites'))
                    <a href="{{ route('storefront.favorites') }}" class="inline-flex items-center gap-2 text-sm text-sf-ink hover:text-elo-primary"><x-ui::icon name="heart" :size="24" />{{ __st('shell.favorite') }}</a>
                @endif
                <x-sf::header.cart class="elo-header-cart" />
                <x-sf::account-link />
            </div>

            @if ($menu !== [])
                <div id="elo-mega" class="elo-mega" data-elo-mega hidden>
                    <div class="mx-auto flex max-w-[1440px] gap-6 px-6 pb-6">
                        <ul class="grid w-[180px] shrink-0 content-start gap-0.5 py-2" role="tablist" aria-orientation="vertical" aria-label="{{ __st('shell.nav.categories') }}">
                            @foreach ($menu as $index => $node)
                                <li role="presentation">
                                    <a href="{{ route('storefront.category', $node['category']->slug) }}" role="tab" id="elo-mega-tab-{{ $node['category']->id }}" aria-controls="elo-mega-panel-{{ $node['category']->id }}" aria-selected="{{ $index === 0 ? 'true' : 'false' }}" class="elo-mega-tab" data-elo-mega-tab>
                                        <span class="truncate">{{ $node['category']->name }}</span><x-ui::icon name="chevron-right" :size="18" class="shrink-0 rtl:-scale-x-100" />
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                        @foreach ($menu as $index => $node)
                            <div id="elo-mega-panel-{{ $node['category']->id }}" role="tabpanel" aria-labelledby="elo-mega-tab-{{ $node['category']->id }}" class="elo-mega-panel" @if ($index !== 0) hidden @endif>
                                <a href="{{ route('storefront.category', $node['category']->slug) }}" class="font-medium text-elo-primary">{{ __st('shell.nav.shop_all', ['category' => $node['category']->name]) }}</a>
                                @foreach ($node['children'] as $child)
                                    <a href="{{ route('storefront.category', $child->slug) }}" class="hover:text-elo-primary">{{ $child->name }}</a>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>

    <div class="lg:hidden">
        @if ($mobileTitle)
            <x-sf::mobile-bar :title="$mobileTitle" :back="$back" />
        @elseif ($home && $context->theme()->variant > 1)
            {{-- v2–v6 phone (m 1:24137 …): dark bar — menu (categories), store logo, white search pill. --}}
            <div class="bg-[#121212] px-4 pb-4 pt-4">
                <div class="mb-4 flex items-center gap-3">
                    @if (Route::has('storefront.categories.index'))
                        <a href="{{ route('storefront.categories.index') }}" class="grid justify-items-center text-white" aria-label="{{ __st('shell.nav.categories') }}"><x-ui::icon name="menu" :size="26" /><span class="text-[9px] leading-none">{{ __st('elora.menu.label') }}</span></a>
                    @endif
                    <x-sf::logo tone="light" />
                    <x-sf::account-link compact class="ms-auto grid size-10 place-items-center rounded-full bg-white !text-elo-primary" />
                </div>
                <x-sf::header.search variant="elora-mobile" />
            </div>
        @elseif ($home)
            <div class="elo-mobile-hero px-4 pb-4 pt-5">
                <div class="mb-4 flex items-center gap-3 text-white">
                    <span class="grid size-11 shrink-0 place-items-center rounded-full bg-white/20" aria-hidden="true"><x-ui::icon name="user" :size="22" /></span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-lg font-semibold">{{ $customer ? __st('elora.mobile.hi', ['name' => \Illuminate\Support\Str::of((string) $customer->name)->before(' ')]) : __st('shell.welcome') }}</p>
                        <button type="button" data-sheet-open="sf-preferences" class="inline-flex max-w-full items-center gap-1 text-xs text-white/90" aria-haspopup="dialog">
                            <span class="truncate">{{ $context->country()?->name ? __st('elora.mobile.deliver_to', ['place' => $context->country()->name]) : __st('shell.preferences.title') }}</span><x-ui::icon name="chevron-down" :size="14" />
                        </button>
                    </div>
                    <x-sf::account-link compact class="grid size-10 place-items-center rounded-full bg-white !text-elo-primary" />
                </div>
                <x-sf::header.search variant="elora-mobile" />
            </div>
        @else
            <div class="flex h-14 items-center gap-3 border-b border-sf-border bg-white px-4">
                <x-sf::logo size="sm" />
                <x-sf::header.search variant="elora-mobile" class="min-w-0 flex-1 [&_div]:h-9 [&_div]:shadow-none [&_div]:bg-[#f4f4f4] [&_input]:text-sm" />
                <x-sf::locale-button tone="dark" compact />
            </div>
        @endif
    </div>
</header>
<x-sf::preferences />
