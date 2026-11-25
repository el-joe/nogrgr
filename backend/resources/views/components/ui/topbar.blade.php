{{--
    Topbar (Figma 35:11474). Desktop 64: sidebar toggle, global search 420 (Ctrl/Cmd K
    command palette over the pages the user can open), language switch (flips dir),
    light/dark toggle, notifications bell, avatar → user menu. Mobile 56: menu, logo,
    search, bell, avatar.
--}}
@props(['user' => null, 'surface' => 'admin', 'role' => null, 'commands' => [], 'notifications' => [], 'unread' => null, 'tag' => null])
@php
    $locales = \App\Shared\Support\Locale::enabled();
    $current = app()->getLocale();
@endphp
<header class="sticky top-0 z-30 border-b border-line bg-surface" {{ $attributes }}>
    <div class="flex h-14 items-center gap-2 px-4 sm:gap-3 lg:h-16 lg:px-6 xl:px-8">
        <x-ui::icon-button icon="panel-left" :label="__('ui.sidebar.toggle')" data-sidebar-toggle aria-controls="app-sidebar" aria-expanded="false" class="hidden lg:inline-flex" />
        <x-ui::icon-button icon="menu" :label="__('ui.sidebar.open_menu')" data-sidebar-toggle aria-controls="app-sidebar" aria-expanded="false" class="lg:hidden" />
        <x-ui::logo variant="light" class="lg:hidden" compact />

        <div data-command id="topbar-search" class="relative hidden w-full max-w-[420px] md:block max-md:data-open:absolute max-md:data-open:inset-x-4 max-md:data-open:top-[64px] max-md:data-open:z-40 max-md:data-open:block max-md:data-open:max-w-none">
            <div class="ui-control min-h-10 border-transparent bg-subtle">
                <x-ui::icon name="search" :size="18" class="text-ink-muted" />
                <label for="global-search" class="sr-only">{{ __('ui.search.label') }}</label>
                <input id="global-search" type="search" data-global-search role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="global-search-list" placeholder="{{ __('ui.search.placeholder') }}" autocomplete="off" class="h-9 text-[13px]">
                <kbd class="hidden rounded-chip border border-line bg-surface px-1.5 py-0.5 font-sans text-[11px] text-ink-secondary lg:inline" dir="ltr">Ctrl K</kbd>
            </div>
            <div class="ui-popover start-0 top-full mt-1 w-full" data-command-results>
                <ul id="global-search-list" role="listbox" aria-label="{{ __('ui.search.results') }}" class="grid gap-0.5"></ul>
            </div>
            <script type="application/json" data-command-items>@json($commands)</script>
        </div>

        <div class="ms-auto flex items-center gap-1 sm:gap-2">
            <x-ui::icon-button icon="search" :label="__('ui.search.label')" class="md:hidden" data-command-toggle="topbar-search" />

            @if (count($locales) > 1)
                <x-ui::dropdown align="end" :width="180" :label="__('ui.language')" class="hidden sm:inline-flex">
                    <x-slot:trigger>
                        <button type="button" data-dropdown-trigger aria-haspopup="menu" aria-expanded="false" class="flex h-10 items-center gap-1.5 rounded-control border border-line px-3 text-sm font-medium text-ink hover:bg-subtle" aria-label="{{ __('ui.language') }}: {{ $locales[$current]['native'] ?? $current }}">
                            <x-ui::icon name="globe" :size="18" class="text-ink-secondary" />
                            <span class="uppercase">{{ $current }}</span>
                            <x-ui::icon name="chevron-down" :size="14" class="text-ink-muted" />
                        </button>
                    </x-slot:trigger>
                    @foreach ($locales as $code => $language)
                        <x-ui::dropdown-item :href="request()->fullUrlWithQuery(['lang' => $code])" :lang="$code" :aria-current="$code === $current ? 'true' : 'false'">
                            <span class="flex items-center justify-between gap-2"><span>{{ $language['native'] }}</span><span class="text-xs uppercase text-ink-muted">{{ $code }}</span></span>
                        </x-ui::dropdown-item>
                    @endforeach
                </x-ui::dropdown>
            @endif

            <div class="hidden items-center gap-0.5 rounded-full bg-subtle p-1 sm:flex" role="group" aria-label="{{ __('ui.theme.label') }}">
                <button type="button" data-theme-set="light" aria-pressed="true" class="grid size-8 place-items-center rounded-full text-ink-muted transition-colors aria-pressed:bg-surface aria-pressed:text-primary aria-pressed:shadow-card" aria-label="{{ __('ui.theme.light') }}" title="{{ __('ui.theme.light') }}">
                    <x-ui::icon name="sun" :size="16" />
                </button>
                <button type="button" data-theme-set="dark" aria-pressed="false" class="grid size-8 place-items-center rounded-full text-ink-muted transition-colors aria-pressed:bg-surface aria-pressed:text-primary aria-pressed:shadow-card" aria-label="{{ __('ui.theme.dark') }}" title="{{ __('ui.theme.dark') }}">
                    <x-ui::icon name="moon" :size="16" />
                </button>
            </div>

            <x-ui::notification-dropdown :items="$notifications" :unread="$unread" />

            <x-ui::user-menu :user="$user" :surface="$surface" :role="$role" />
        </div>
    </div>
</header>
