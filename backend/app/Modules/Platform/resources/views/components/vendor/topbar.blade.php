{{--
    Vendor topbar (Tenant Panel Layout, Dashboard 12:11267): sidebar toggle, search
    (Ctrl/Cmd K command palette over the pages the user may open), then language,
    notifications bell (light-orange square), dark / light toggle (dark square), view
    store and the user card. Mobile 56: menu, search, bell, user.
--}}
@props(['shell', 'storeUrl' => null])
@php
    $locales = \App\Shared\Support\Locale::enabled();
    $current = app()->getLocale();
    $feed = $shell['notifications'];
@endphp
<header class="vnd-topbar sticky top-0 z-30 border-b border-line bg-surface">
    <div class="flex h-14 items-center gap-2 px-4 sm:gap-3 lg:h-[73px] lg:px-6">
        <x-ui::icon-button icon="panel-left" :label="__('ui.sidebar.toggle')" data-sidebar-toggle aria-controls="app-sidebar" aria-expanded="false" class="hidden lg:inline-flex" />
        <x-ui::icon-button icon="menu" :label="__('ui.sidebar.open_menu')" data-sidebar-toggle aria-controls="app-sidebar" aria-expanded="false" class="lg:hidden" />

        <div data-command id="topbar-search" class="relative hidden w-full max-w-[382px] md:block max-md:data-open:absolute max-md:data-open:inset-x-4 max-md:data-open:top-[64px] max-md:data-open:z-40 max-md:data-open:block max-md:data-open:max-w-none lg:ms-[86px]">
            <div class="ui-control min-h-12 rounded-[12px] border-transparent bg-subtle px-4">
                <x-ui::icon name="search" :size="22" class="text-ink-secondary" />
                <label for="global-search" class="sr-only">{{ __('ui.search.label') }}</label>
                <input id="global-search" type="search" data-global-search role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="global-search-list" placeholder="{{ __('platform::vendor.search') }}" autocomplete="off" class="h-10 text-base">
                <kbd class="hidden whitespace-nowrap rounded-chip border border-line bg-surface px-1.5 py-0.5 font-sans text-[11px] text-ink-secondary xl:inline" dir="ltr">Ctrl K</kbd>
            </div>
            <div class="ui-popover start-0 top-full mt-1 w-full" data-command-results>
                <ul id="global-search-list" role="listbox" aria-label="{{ __('ui.search.results') }}" class="grid gap-0.5"></ul>
            </div>
            <script type="application/json" data-command-items>@json($shell['commands'])</script>
        </div>

        <div class="ms-auto flex items-center gap-2 sm:gap-3">
            <x-ui::icon-button icon="search" :label="__('ui.search.label')" class="md:hidden" data-command-toggle="topbar-search" />

            @if (count($locales) > 1)
                <x-ui::dropdown align="end" :width="180" :label="__('ui.language')" class="hidden sm:inline-flex">
                    <x-slot:trigger>
                        <button type="button" data-dropdown-trigger aria-haspopup="menu" aria-expanded="false" class="vnd-square bg-subtle text-ink-secondary hover:text-ink" aria-label="{{ __('ui.language') }}: {{ $locales[$current]['native'] ?? $current }}" title="{{ __('ui.language') }}">
                            <span class="text-[13px] font-semibold uppercase">{{ $current }}</span>
                        </button>
                    </x-slot:trigger>
                    @foreach ($locales as $code => $language)
                        <x-ui::dropdown-item :href="request()->fullUrlWithQuery(['lang' => $code])" :lang="$code" :aria-current="$code === $current ? 'true' : 'false'">
                            <span class="flex items-center justify-between gap-2"><span>{{ $language['native'] }}</span><span class="text-xs uppercase text-ink-muted">{{ $code }}</span></span>
                        </x-ui::dropdown-item>
                    @endforeach
                </x-ui::dropdown>
            @endif

            <x-ui::notification-dropdown
                class="vnd-bell"
                :items="$feed['items']"
                :unread="$feed['unread']"
                :mark-all-url="$feed['markAllUrl']"
                :view-all-url="$feed['viewAllUrl']"
                :latest-url="$feed['latestUrl']"
                :realtime="$feed['realtime']"
            />

            <button type="button" data-theme-toggle aria-pressed="false" class="vnd-square vnd-theme hidden bg-inverse text-on-inverse sm:grid" aria-label="{{ __('ui.theme.label') }}" title="{{ __('ui.theme.label') }}">
                <x-ui::icon name="moon" :size="22" class="dark:hidden" />
                <x-ui::icon name="sun" :size="22" class="hidden dark:block" />
            </button>

            @if ($storeUrl)
                <a href="{{ $storeUrl }}" target="_blank" rel="noopener" class="vnd-square hidden border border-line text-ink-secondary hover:text-ink md:grid" aria-label="{{ __('platform::vendor.view_store') }}" title="{{ __('platform::vendor.view_store') }}">
                    <x-ui::icon name="eye" :size="20" />
                </a>
            @endif

            <x-ui::user-menu class="vnd-user" :user="$shell['user']" surface="vendor" :role="$shell['role']" />
        </div>
    </div>
</header>
