{{--
    Vendor topbar — client Tenant Panel top bar 12:15522 (decision #101). Desktop: white,
    74 high, 24 side padding: "Select your country" (#F5F5F5 r8 261×48; picks the selling
    country of the dashboard live-store preview, VEN-009f), search (#F5F5F5 r8 382×48;
    Ctrl/Cmd K command palette over the pages the user may open), then the 40 px boxes —
    language (the only addition, 180:28706), bell (#FFF1E6 / #FD6F04) and dark mode
    (#0B0B0B) — and the store menu (#E7E7E7 r8: store avatar, name, "Vendor Workspace").
    Mobile (< 1024, client mobile frames e.g. 12:23476): black bar with r24 bottom
    corners — menu box, logo, bell, dark mode, store avatar.
    The store menu holds My account, Notifications, Support, Help center, Language and
    Sign out (each only with its route + permission).
--}}
@props(['shell', 'storeUrl' => null, 'storeName' => null, 'logoUrl' => null])
@php
    $locales = \App\Shared\Support\Locale::enabled();
    $current = app()->getLocale();
    $nextLocale = collect(array_keys($locales))->first(fn ($code) => $code !== $current) ?? $current;
    $feed = $shell['notifications'];
    $user = $shell['user'];
    $panel = app(\App\Shared\Navigation\PanelShell::class);
    $can = fn (string $permission): bool => $user !== null && method_exists($user, 'can') && $user->can($permission);
    $link = fn (string $route, string $permission): ?string => $can($permission) ? $panel->url('vendor', $route) : null;

    $countries = [];
    $dashboardUrl = $panel->url('vendor', 'dashboard');

    if ($dashboardUrl !== null && $can('store.themes.view') && class_exists(\App\Modules\Storefront\Services\Themes\ThemePageService::class)) {
        try {
            $countries = app(\App\Modules\Storefront\Services\Themes\ThemePageService::class)->countries();
        } catch (\Throwable $exception) {
            report($exception);
            $countries = [];
        }
    }

    $currentCountry = request()->routeIs('vendor.dashboard') ? filter_var(request()->query('country'), FILTER_VALIDATE_INT) : false;
    $countryLabel = is_int($currentCountry) && isset($countries[$currentCountry]) ? $countries[$currentCountry] : __('platform::vendor.topbar.country');
    $countriesUrl = $link('settings.countries.edit', 'settings.countries.view');

    $menu = array_filter([
        ['icon' => 'user', 'label' => __('platform::vendor.topbar.account'), 'url' => $link('settings.account', 'dashboard.view')],
        ['icon' => 'bell', 'label' => __('navigation.admin.notifications'), 'url' => $link('notifications.index', 'notifications.view')],
        ['icon' => 'lifebuoy', 'label' => __('navigation.vendor.support'), 'url' => $link('support.index', 'support.view')],
        ['icon' => 'file', 'label' => __('navigation.vendor.help_center'), 'url' => $link('help.index', 'dashboard.view')],
        ['icon' => 'eye', 'label' => __('platform::vendor.topbar.open_store'), 'url' => $storeUrl, 'external' => true],
    ], fn (array $item): bool => $item['url'] !== null);
    $logoutUrl = $panel->url('vendor', 'logout');
    $initials = \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr((string) $storeName, 0, 2));
    $brand = fn (string $file) => \Illuminate\Support\Facades\Vite::asset('resources/images/vendor/brand/'.$file);
@endphp
<header class="vnd-topbar sticky top-0 z-30">
    <div class="vnd-topbar-row">
        <button type="button" data-sidebar-toggle aria-controls="app-sidebar" aria-expanded="false" class="vnd-square vnd-topbar-menu lg:hidden" aria-label="{{ __('ui.sidebar.open_menu') }}" title="{{ __('ui.sidebar.open_menu') }}">
            <x-ui::icon name="menu" :size="22" />
        </button>

        <a @if ($dashboardUrl) href="{{ $dashboardUrl }}" @endif class="vnd-logo vnd-topbar-logo lg:hidden" dir="ltr" aria-label="{{ __('ui.sidebar.home') }}">
            <img src="{{ $brand('mark.webp') }}" alt="" width="29" height="30" class="vnd-logo-mark">
            <img src="{{ $brand('wordmark-dark.webp') }}" alt="Nogrgr" width="79" height="18" class="vnd-logo-word max-[359px]:hidden">
        </a>

        @if ($countries !== [])
            <div class="hidden lg:block">
            <x-ui::dropdown align="start" width="261" :label="__('platform::vendor.topbar.country')">
                <x-slot:trigger>
                    <button type="button" data-dropdown-trigger aria-haspopup="menu" aria-expanded="false" class="vnd-field-box vnd-country" title="{{ __('platform::vendor.topbar.country_hint') }}">
                        <x-ui::icon name="tp-globe" :size="24" />
                        <span class="min-w-0 flex-1 truncate text-start">{{ $countryLabel }}</span>
                        <x-ui::icon name="tp-chevron-down" :size="24" class="vnd-country-chevron" />
                    </button>
                </x-slot:trigger>
                <p class="px-2 pb-2 pt-1 text-xs text-ink-secondary">{{ __('platform::vendor.topbar.country_hint') }}</p>
                <x-ui::dropdown-item icon="globe" :href="$dashboardUrl.'#live-store-title'" :aria-current="$currentCountry === false || $currentCountry === null ? 'true' : 'false'">{{ __('platform::vendor.topbar.all_countries') }}</x-ui::dropdown-item>
                @foreach ($countries as $id => $name)
                    <x-ui::dropdown-item icon="map-pin" :href="vendor_route('dashboard', ['country' => $id]).'#live-store-title'" :aria-current="$currentCountry === $id ? 'true' : 'false'">{{ $name }}</x-ui::dropdown-item>
                @endforeach
                @if ($countriesUrl)
                    <x-ui::dropdown-divider />
                    <x-ui::dropdown-item icon="settings" :href="$countriesUrl">{{ __('platform::vendor.topbar.manage_countries') }}</x-ui::dropdown-item>
                @endif
            </x-ui::dropdown>
            </div>
        @endif

        <div data-command id="topbar-search" class="vnd-search relative hidden md:block max-md:data-open:absolute max-md:data-open:inset-x-4 max-md:data-open:top-[72px] max-md:data-open:z-40 max-md:data-open:block max-md:data-open:max-w-none">
            <div class="ui-control vnd-field-box">
                <x-ui::icon name="tp-search" :size="24" class="shrink-0" />
                <label for="global-search" class="sr-only">{{ __('ui.search.label') }}</label>
                <input id="global-search" type="search" data-global-search role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="global-search-list" aria-keyshortcuts="Control+K Meta+K" placeholder="{{ __('platform::vendor.search') }}" autocomplete="off">
            </div>
            <div class="ui-popover start-0 top-full mt-1 w-full" data-command-results>
                <ul id="global-search-list" role="listbox" aria-label="{{ __('ui.search.results') }}" class="grid gap-0.5"></ul>
            </div>
            <script type="application/json" data-command-items>@json($shell['commands'])</script>
        </div>

        <div class="vnd-topbar-end">
            <div class="flex items-center gap-2">
                <button type="button" class="vnd-square vnd-topbar-muted hidden sm:grid md:hidden" data-command-toggle="topbar-search" aria-label="{{ __('ui.search.label') }}" title="{{ __('ui.search.label') }}">
                    <x-ui::icon name="tp-search" :size="22" />
                </button>

                @if (count($locales) > 1)
                    <div class="hidden sm:block">
                    <x-ui::dropdown align="end" :width="180" :label="__('ui.language')">
                        <x-slot:trigger>
                            <button type="button" data-dropdown-trigger aria-haspopup="menu" aria-expanded="false" class="vnd-square vnd-topbar-muted" aria-label="{{ __('ui.language') }}: {{ $locales[$current]['native'] ?? $current }}" title="{{ __('ui.language') }}">
                                <span class="text-base font-medium" lang="{{ $nextLocale }}">{{ $nextLocale === 'ar' ? 'ع' : \Illuminate\Support\Str::upper($nextLocale) }}</span>
                            </button>
                        </x-slot:trigger>
                        @foreach ($locales as $code => $language)
                            <x-ui::dropdown-item :href="request()->fullUrlWithQuery(['lang' => $code])" :lang="$code" :aria-current="$code === $current ? 'true' : 'false'">
                                <span class="flex items-center justify-between gap-2"><span>{{ $language['native'] }}</span><span class="text-xs uppercase text-ink-muted">{{ $code }}</span></span>
                            </x-ui::dropdown-item>
                        @endforeach
                    </x-ui::dropdown>
                    </div>
                @endif

                <x-ui::notification-dropdown
                    class="vnd-bell"
                    trigger-icon="tp-bell"
                    :trigger-icon-size="24"
                    :items="$feed['items']"
                    :unread="$feed['unread']"
                    :mark-all-url="$feed['markAllUrl']"
                    :view-all-url="$feed['viewAllUrl']"
                    :latest-url="$feed['latestUrl']"
                    :realtime="$feed['realtime']"
                />

                <button type="button" data-theme-toggle aria-pressed="false" class="vnd-square vnd-theme" aria-label="{{ __('ui.theme.label') }}" title="{{ __('ui.theme.label') }}">
                    <x-ui::icon name="tp-moon" :size="24" class="dark:hidden" />
                    <x-ui::icon name="sun" :size="22" class="hidden dark:block" />
                </button>
            </div>

            <x-ui::dropdown align="end" :width="260" :label="__('platform::vendor.topbar.store_menu')" class="vnd-store-menu">
                <x-slot:trigger>
                    <button type="button" data-dropdown-trigger aria-haspopup="menu" aria-expanded="false" class="vnd-store-trigger" aria-label="{{ __('platform::vendor.topbar.store_menu') }}: {{ $storeName }}">
                        <span class="vnd-store-avatar size-[34px] text-xs">
                            @if ($logoUrl)
                                <img src="{{ $logoUrl }}" alt="" class="size-full bg-media object-contain" width="34" height="34">
                            @else
                                {{ $initials }}
                            @endif
                        </span>
                        <span class="vnd-store-trigger-text hidden min-w-0 lg:grid">
                            <span class="truncate text-base font-semibold leading-normal text-ink">{{ $storeName }}</span>
                            <span class="truncate text-xs leading-normal text-ink-secondary">{{ __('platform::vendor.workspace') }}</span>
                        </span>
                        <x-ui::icon name="tp-chevron-down" :size="24" class="hidden shrink-0 text-ink lg:block" />
                    </button>
                </x-slot:trigger>

                <div class="mb-2 flex items-center gap-3 border-b border-line px-2 pb-3 pt-1">
                    <x-ui::avatar :name="$user?->name ?? (string) $storeName" :size="36" />
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-ink">{{ $user?->name }}</p>
                        @if ($shell['role'])
                            <p class="truncate text-xs text-ink-secondary">{{ $shell['role'] }}</p>
                        @endif
                    </div>
                </div>
                @foreach ($menu as $item)
                    <x-ui::dropdown-item :icon="$item['icon']" :href="$item['url']" :target="($item['external'] ?? false) ? '_blank' : null" :rel="($item['external'] ?? false) ? 'noopener' : null">{{ $item['label'] }}</x-ui::dropdown-item>
                @endforeach
                @if (count($locales) > 1)
                    <x-ui::dropdown-item icon="languages" :href="request()->fullUrlWithQuery(['lang' => $nextLocale])" :lang="$nextLocale" class="sm:hidden">
                        {{ __('ui.user.language', ['language' => $locales[$current]['native'] ?? $current]) }}
                    </x-ui::dropdown-item>
                @endif
                @if ($logoutUrl)
                    <x-ui::dropdown-divider />
                    <form method="POST" action="{{ $logoutUrl }}">
                        @csrf
                        <x-ui::dropdown-item type="submit" icon="log-out" tone="danger">{{ __('ui.user.sign_out') }}</x-ui::dropdown-item>
                    </form>
                @endif
            </x-ui::dropdown>
        </div>
    </div>
</header>
