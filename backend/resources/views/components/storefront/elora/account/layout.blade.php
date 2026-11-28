{{--
    Elora account shell (client Figma Elora "Orders" 1:18739 / "Order details" 1:18987, phone
    m 1:23374 / 1:23600): breadcrumb, side bar (customer card with "edit", "My Orders" status
    list, "Settings" links) on desktop; on phones and tablets a title / back header and a
    swipeable tab strip. Same links, banners and logout as the shared account shell
    (x-sf::account.layout delegates here for the Elora family, so profile, addresses,
    notifications, returns and support use it too).
    heading: show the desktop h1 (the client's order frames have none).
    tabs: the account-section strip below lg (the order list passes its status tabs as `strip`).
--}}
@props(['title', 'active' => null, 'page' => null, 'crumb' => null, 'seo' => null, 'back' => null,
    'header' => null, 'actions' => null, 'heading' => true, 'tabs' => true, 'orderTab' => null,
    'strip' => null, 'mobileHead' => null])
@php
    $customer = auth('customer')->user();
    $has = static fn (string $route): bool => \Illuminate\Support\Facades\Route::has($route);
    $settings = collect([
        'profile' => ['route' => 'storefront.account.profile', 'label' => 'elora_pages.account.personal'],
        'addresses' => ['route' => 'storefront.account.addresses', 'label' => 'elora_pages.account.addresses'],
        'returns' => ['route' => 'storefront.returns.index', 'label' => 'elora_pages.account.returns'],
        'wishlist' => ['route' => 'storefront.favorites', 'label' => 'elora_pages.account.wishlist'],
        'notifications' => ['route' => 'storefront.account.notifications', 'label' => 'elora_pages.account.notifications'],
        'support' => ['route' => 'storefront.support.index', 'label' => 'elora_pages.account.help'],
    ])->filter(fn (array $link): bool => $has($link['route']));
    $sections = collect([
        'profile' => ['route' => 'storefront.account.profile', 'label' => 'identity::account.nav.profile'],
        'orders' => ['route' => 'storefront.orders.index', 'label' => 'account.nav.orders'],
        'returns' => ['route' => 'storefront.returns.index', 'label' => 'account.nav.returns'],
        'addresses' => ['route' => 'storefront.account.addresses', 'label' => 'identity::account.nav.addresses'],
        'wishlist' => ['route' => 'storefront.favorites', 'label' => 'account.nav.wishlist'],
        'notifications' => ['route' => 'storefront.account.notifications', 'label' => 'identity::account.nav.notifications'],
        'support' => ['route' => 'storefront.support.index', 'label' => 'support.tickets.nav'],
    ])->filter(fn (array $link): bool => $has($link['route']));
    $home = $has('storefront.home') ? route('storefront.home') : null;
    $accountHome = $has('storefront.account.profile') ? route('storefront.account.profile') : null;
    $orderStatuses = $has('storefront.orders.index') ? array_keys(\App\Modules\Storefront\Services\Account\AccountOrderPages::TABS) : [];
    $hasHeader = $header !== null && trim((string) $header) !== '';
@endphp
<x-sf::layout :title="$title" :nav="$active === 'orders' ? 'orders' : 'account'" :page="$page" :seo="$seo">
    <div class="eac" data-elo-account="{{ $page ?? $active }}" data-sf-account>
        <nav aria-label="{{ __st('shell.breadcrumb') }}" class="eac-crumbs">
            <ol>
                @if ($home)<li><a href="{{ $home }}">{{ __st('shell.home') }}</a><x-ui::icon name="chevron-right" :size="16" class="rtl:-scale-x-100" /></li>@endif
                @if ($accountHome)<li><a href="{{ $accountHome }}">{{ __st('elora_pages.account.crumb') }}</a><x-ui::icon name="chevron-right" :size="16" class="rtl:-scale-x-100" /></li>@endif
                @if ($back && $crumb && $crumb !== $title && $active === 'orders' && $has('storefront.orders.index'))
                    <li><a href="{{ route('storefront.orders.index') }}">{{ $crumb }}</a><x-ui::icon name="chevron-right" :size="16" class="rtl:-scale-x-100" /></li>
                    <li><span aria-current="page">{{ $title }}</span></li>
                @else
                    <li><span aria-current="page">{{ $crumb ?? $title }}</span></li>
                @endif
            </ol>
        </nav>

        {{-- Phones / tablets: title (with back on sub-pages) and the swipeable strip. --}}
        <div class="eac-mhead">
            @if ($mobileHead)
                {{ $mobileHead }}
            @else
                <div class="eac-mhead-row">
                    @if ($back)
                        <a href="{{ $back }}" class="eac-back mo-press" aria-label="{{ __st('shell.back') }}"><x-ui::icon name="elo-back-chevron" :size="24" /></a>
                    @endif
                    <p class="eac-mhead-title" aria-hidden="true">{{ $title }}</p>
                    @if ($actions !== null && trim((string) $actions) !== '')<div class="eac-mhead-actions">{{ $actions }}</div>@endif
                </div>
            @endif
            @if ($strip)
                {{ $strip }}
            @elseif ($tabs)
                <nav class="eac-strip" aria-label="{{ __st('elora_pages.account.sections') }}" data-elo-drag>
                    @foreach ($sections as $key => $link)
                        <a href="{{ route($link['route']) }}" @if ($active === $key) aria-current="page" @endif>{{ __st($link['label']) }}</a>
                    @endforeach
                </nav>
            @endif
        </div>

        <div class="eac-grid">
            <aside class="eac-side" aria-label="{{ __('identity::account.nav.label') }}">
                @if ($customer)
                    <div class="eac-box eac-user">
                        <p class="eac-user-name" dir="auto">{{ $customer->name }}</p>
                        <p class="eac-user-mail" dir="ltr">{{ $customer->email }}</p>
                        @if ($accountHome)<a href="{{ $accountHome }}" class="eac-user-edit mo-press">{{ __st('elora_pages.account.edit') }}</a>@endif
                    </div>
                @endif
                @if ($orderStatuses !== [])
                    <nav class="eac-box eac-menu" aria-labelledby="eac-orders-title">
                        <p class="eac-menu-title" id="eac-orders-title"><a href="{{ route('storefront.orders.index') }}">{{ __st('elora_pages.account.my_orders') }}</a></p>
                        <ul>
                            @foreach ($orderStatuses as $key)
                                <li><a href="{{ route('storefront.orders.index', $key === 'all' ? [] : ['status' => $key]) }}" @if ($active === 'orders' && $orderTab === $key) aria-current="page" @endif>{{ __st('orders.tabs.'.$key) }}</a></li>
                            @endforeach
                        </ul>
                    </nav>
                @endif
                <nav class="eac-box eac-menu eac-settings" aria-labelledby="eac-settings-title">
                    <p class="eac-menu-title" id="eac-settings-title">{{ __st('elora_pages.account.settings') }}</p>
                    <ul>
                        @foreach ($settings as $key => $link)
                            <li><a href="{{ route($link['route']) }}" @if ($active === $key) aria-current="page" @endif>{{ __st($link['label']) }}</a></li>
                        @endforeach
                        <li>
                            <form method="POST" action="{{ route('storefront.account.logout') }}">
                                @csrf
                                <button type="submit" class="eac-logout">{{ __('identity::auth.logout') }}</button>
                            </form>
                        </li>
                    </ul>
                </nav>
            </aside>

            <div class="eac-main">
                @if ($hasHeader)
                    <div class="eac-header">{{ $header }}</div>
                @elseif ($heading)
                    <div class="eac-head">
                        <h1>{{ $title }}</h1>
                        @if ($actions !== null && trim((string) $actions) !== '')<div class="eac-head-actions">{{ $actions }}</div>@endif
                    </div>
                @else
                    <h1 class="sr-only">{{ $title }}</h1>
                @endif
                @if (session('status'))
                    <x-sf::alert tone="success">{{ session('status') }}</x-sf::alert>
                @endif
                @if ($customer && $customer->email_verified_at === null && $has('storefront.account.verification'))
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1 rounded-lg bg-sf-warning-bg px-3 py-2.5 text-sm text-sf-ink" data-verify-banner>
                        <x-ui::icon name="mail" :size="18" class="shrink-0 text-sf-warning" />
                        <p class="flex-1">{{ __st('auth.verify_banner') }}</p>
                        <a href="{{ route('storefront.account.verification', ['redirect' => request()->fullUrl()]) }}" class="sf-link text-sm">{{ __st('auth.verify_now') }}</a>
                    </div>
                @endif
                {{ $slot }}
                @if ($tabs && ! $strip)
                    <form method="POST" action="{{ route('storefront.account.logout') }}" class="lg:hidden">
                        @csrf
                        <button type="submit" class="sf-btn sf-btn-danger-outline w-full"><x-ui::icon name="log-out" :size="18" />{{ __('identity::auth.logout') }}</button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</x-sf::layout>
