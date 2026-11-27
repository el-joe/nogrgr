{{--
    Account shell (Figma Storefront additions §5–8): breadcrumb, customer card + side
    navigation on desktop, pill tabs under the app bar on phones. Only links whose
    routes exist are rendered (orders, returns, wishlist appear when they ship).
    <x-sf::account.layout :title="…" active="profile"> <x-slot:actions>…</x-slot:actions> … </x-sf::account.layout>
    <x-slot:header> replaces the desktop h1 row (order / return details: number + badge + tabs).
    :crumb overrides the last breadcrumb (defaults to the title).
--}}
@props(['title', 'active' => null, 'page' => null, 'crumb' => null, 'seo' => null, 'back' => null])
@php
    $customer = auth('customer')->user();
    $links = collect([
        'orders' => ['route' => 'storefront.orders.index', 'icon' => 'cart', 'label' => 'account.nav.orders'],
        'returns' => ['route' => 'storefront.returns.index', 'icon' => 'return', 'label' => 'account.nav.returns'],
        'wishlist' => ['route' => 'storefront.favorites', 'icon' => 'star', 'label' => 'account.nav.wishlist'],
        'support' => ['route' => 'storefront.support.index', 'icon' => 'lifebuoy', 'label' => 'support.tickets.nav'],
        'addresses' => ['route' => 'storefront.account.addresses', 'icon' => 'map', 'label' => 'identity::account.nav.addresses'],
        'profile' => ['route' => 'storefront.account.profile', 'icon' => 'users', 'label' => 'identity::account.nav.profile'],
        'notifications' => ['route' => 'storefront.account.notifications', 'icon' => 'bell', 'label' => 'identity::account.nav.notifications'],
    ])->filter(fn ($link) => Route::has($link['route']));
    $home = Route::has('storefront.home') ? route('storefront.home') : null;
    $accountHome = Route::has('storefront.account.profile') ? route('storefront.account.profile') : null;
@endphp
<x-sf::layout :title="$title" :mobile-title="$title" :back="$back ?? $home" nav="account" :page="$page" :seo="$seo">
    <div class="mx-auto max-w-[1248px] px-4 pb-16 pt-4 sm:px-6 lg:pb-20 lg:pt-8" data-sf-account>
        <nav aria-label="{{ __st('shell.breadcrumb') }}" class="mb-6 hidden text-xs text-sf-muted lg:block">
            <ol class="flex items-center gap-2">
                @if ($home)<li><a href="{{ $home }}" class="hover:text-sf-ink">{{ __st('shell.home') }}</a></li><li aria-hidden="true"><x-ui::icon name="chevron-right" :size="12" class="rtl:-scale-x-100" /></li>@endif
                <li>@if ($accountHome)<a href="{{ $accountHome }}" class="hover:text-sf-ink">{{ __('identity::account.nav.label') }}</a>@else{{ __('identity::account.nav.label') }}@endif</li>
                <li aria-hidden="true"><x-ui::icon name="chevron-right" :size="12" class="rtl:-scale-x-100" /></li>
                <li aria-current="page" class="text-sf-ink">{{ $crumb ?? $title }}</li>
            </ol>
        </nav>

        <nav aria-label="{{ __('identity::account.nav.label') }}" class="-mx-4 mb-4 flex snap-x gap-2 overflow-x-auto px-4 pb-1 [scrollbar-width:none] lg:hidden" data-account-tabs>
            @foreach ($links as $key => $link)
                <a href="{{ route($link['route']) }}" class="sf-pill-tab" @if ($active === $key) aria-current="page" @endif>{{ __st($link['label']) }}</a>
            @endforeach
        </nav>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-[260px_minmax(0,1fr)]">
            <aside class="hidden content-start gap-3 lg:grid" aria-label="{{ __('identity::account.nav.label') }}">
                @if ($customer)
                    <div class="sf-card grid gap-1 px-4 py-4">
                        <p class="truncate text-sm font-medium text-sf-ink">{{ $customer->name }}</p>
                        <p class="truncate text-xs text-sf-muted" dir="ltr">{{ $customer->email }}</p>
                    </div>
                @endif
                <nav class="sf-card grid gap-0.5 p-2">
                    @foreach ($links as $key => $link)
                        <a href="{{ route($link['route']) }}" class="sf-account-link" @if ($active === $key) aria-current="page" @endif><x-ui::icon :name="$link['icon']" :size="18" />{{ __st($link['label']) }}</a>
                    @endforeach
                    <form method="POST" action="{{ route('storefront.account.logout') }}">
                        @csrf
                        <button type="submit" class="sf-account-link w-full text-sf-danger"><x-ui::icon name="log-out" :size="18" />{{ __('identity::auth.logout') }}</button>
                    </form>
                </nav>
            </aside>

            <div class="grid min-w-0 content-start gap-5" data-reveal="fade-up">
                @isset($header)
                    {{ $header }}
                @else
                    <div class="flex flex-wrap items-center justify-between gap-3 empty:hidden">
                        <h1 class="hidden text-[22px] font-semibold leading-[30px] text-sf-ink lg:block">{{ $title }}</h1>
                        {{ $actions ?? '' }}
                    </div>
                @endisset
                @if (session('status'))
                    <x-sf::alert tone="success">{{ session('status') }}</x-sf::alert>
                @endif
                @if ($customer && $customer->email_verified_at === null && Route::has('storefront.account.verification'))
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1 rounded-lg bg-sf-warning-bg px-3 py-2.5 text-sm text-sf-ink" data-verify-banner>
                        <x-ui::icon name="mail" :size="18" class="shrink-0 text-sf-warning" />
                        <p class="flex-1">{{ __st('auth.verify_banner') }}</p>
                        <a href="{{ route('storefront.account.verification', ['redirect' => request()->fullUrl()]) }}" class="sf-link text-sm">{{ __st('auth.verify_now') }}</a>
                    </div>
                @endif
                {{ $slot }}
                <form method="POST" action="{{ route('storefront.account.logout') }}" class="lg:hidden">
                    @csrf
                    <button type="submit" class="sf-btn sf-btn-danger-outline w-full"><x-ui::icon name="log-out" :size="18" />{{ __('identity::auth.logout') }}</button>
                </form>
            </div>
        </div>
    </div>
</x-sf::layout>
