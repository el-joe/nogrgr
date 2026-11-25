{{-- Souqify navbar (Figma 1:81294): dark utility strip + white bar — logo, categories, search, cart, account. --}}
@props(['mobileTitle' => null, 'back' => null])
<header data-sticky-header class="hidden lg:block">
    <div class="h-9 bg-sf-topbar text-white">
        <div class="mx-auto flex h-full max-w-[1440px] items-center justify-end gap-4 px-8 text-xs">
            <x-sf::lang-switch tone="light" />
        </div>
    </div>
    <div class="bg-white">
        <div class="mx-auto flex h-[88px] max-w-[1440px] items-center gap-5 px-8">
            <x-sf::logo tone="accent" size="lg" data-header-shrink />
            @if (Route::has('storefront.categories.index'))
                <a href="{{ route('storefront.categories.index') }}" class="inline-flex h-[52px] items-center gap-3 rounded-sm bg-sf-accent px-5 text-sm font-medium text-white"><x-ui::icon name="menu" :size="18" />{{ __('storefront::shell.nav.shop_by_categories') }}</a>
            @endif
            <x-sf::header.search variant="souqify" class="flex-1" />
            <div class="ms-auto flex items-center gap-8">
                <x-sf::header.cart />
                @php($customer = auth('customer')->user())
                @if (Route::has('storefront.account.login'))
                    <a href="{{ $customer ? route('storefront.account.profile') : route('storefront.account.login') }}" class="inline-flex items-center gap-2 text-sm uppercase tracking-wide text-sf-ink">
                        <x-ui::icon name="users" :size="24" />{{ $customer ? $customer->name : __('storefront::shell.account') }}
                    </a>
                @endif
            </div>
        </div>
    </div>
</header>
<div class="lg:hidden">
    @if ($mobileTitle)
        <x-sf::mobile-bar :title="$mobileTitle" :back="$back" />
    @else
        <div class="flex h-14 items-center gap-3 border-b border-sf-border bg-white px-4">
            <x-sf::logo tone="accent" size="sm" />
            <div class="ms-auto flex items-center gap-4"><x-sf::lang-switch compact /><x-sf::account-link compact /></div>
        </div>
    @endif
</div>
