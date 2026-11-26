{{--
    Vendor side menu — client Tenant Panel "Side menu" 12:15498 (decision #101): white,
    236 wide, logo lock-up (mark 39×40 + wordmark 105×24), the client's 11 flat items
    (`Frame 102` 12:10990 active / 12:10996 idle: 8 px start marker + 48 px pill, the
    active pill #FD6F04 runs into the inline-end edge), store card #F5F5F5 at the foot.
    An item with sub-pages links to its first reachable sub-page; while one of them is
    active they are listed indented under it (Figma 180:28706, active #FFF1E6 / #FD6F04).
    Drawer / collapse / keyboard behaviour: resources/js/panels/ui/sidebar.js
    (data-sidebar-pinned = never collapsed on wide screens).
--}}
@props(['sections' => [], 'counts' => [], 'homeUrl' => null, 'storeName' => null, 'logoUrl' => null])
@php
    $count = fn (?string $key) => $key !== null ? (int) ($counts[$key] ?? 0) : 0;
    $display = fn (int $value) => $value > 99 ? '99+' : (string) $value;
    $logoutUrl = app(\App\Shared\Navigation\PanelShell::class)->url('vendor', 'logout');
    $brand = fn (string $file) => \Illuminate\Support\Facades\Vite::asset('resources/images/vendor/brand/'.$file);
@endphp
<aside id="app-sidebar" class="ui-sidebar vnd-sidebar" data-sidebar-root data-sidebar-pinned aria-label="{{ __('ui.sidebar.label') }}">
    <div class="ui-sidebar-head vnd-sidebar-head">
        <a @if ($homeUrl) href="{{ $homeUrl }}" @endif class="vnd-logo" dir="ltr" aria-label="{{ __('ui.sidebar.home') }}">
            <img src="{{ $brand('mark.webp') }}" alt="" width="39" height="40" class="vnd-logo-mark">
            <img src="{{ $brand('wordmark.webp') }}" alt="Nogrgr" width="105" height="24" class="ui-sidebar-wordmark vnd-logo-word dark:hidden">
            <img src="{{ $brand('wordmark-dark.webp') }}" alt="Nogrgr" width="105" height="24" class="ui-sidebar-wordmark vnd-logo-word hidden dark:block">
        </a>
        <button type="button" data-sidebar-toggle class="vnd-square ms-auto text-sidebar-ink hover:bg-subtle lg:hidden" aria-controls="app-sidebar" aria-label="{{ __('ui.sidebar.close_menu') }}" title="{{ __('ui.sidebar.close_menu') }}">
            <x-ui::icon name="x" :size="20" />
        </button>
    </div>

    <nav class="ui-sidebar-nav vnd-sidebar-nav min-h-0 flex-1 overflow-y-auto overflow-x-hidden" aria-label="{{ __('ui.sidebar.main') }}">
        @foreach ($sections as $section)
            <ul class="grid gap-0.5" role="list" @if ($section['label']) aria-label="{{ $section['label'] }}" @endif>
                @foreach ($section['items'] as $item)
                    @php
                        $itemCount = $count($item['badge']);
                        $url = $item['url'] ?? ($item['children'][0]['url'] ?? null);
                        $listId = 'nav-sub-'.\Illuminate\Support\Str::slug($item['key']);
                        $showChildren = $item['children'] !== [] && $item['active'];
                    @endphp
                    <li data-nav-item="{{ $item['key'] }}">
                        <a href="{{ $url }}" @class(['ui-nav-link vnd-nav-link', 'is-active' => $item['active']]) @if ($item['active'] && ! $showChildren) aria-current="page" @endif @if ($showChildren) aria-controls="{{ $listId }}" aria-expanded="true" @endif title="{{ $item['label'] }}">
                            <span class="vnd-nav-rail" aria-hidden="true"></span>
                            <span class="vnd-nav-pill">
                                @if ($item['icon'])<x-ui::icon :name="$item['icon']" :size="24" />@endif
                                <span class="ui-nav-label min-w-0 flex-1 truncate">{{ $item['label'] }}</span>
                                @if ($itemCount > 0)
                                    <span class="ui-nav-count vnd-nav-count" data-nav-badge="{{ $item['badge'] }}">{{ $display($itemCount) }}</span>
                                @endif
                            </span>
                        </a>
                        @if ($showChildren)
                            <ul id="{{ $listId }}" class="ui-nav-children vnd-nav-children" role="list" aria-label="{{ $item['label'] }}">
                                @foreach ($item['children'] as $child)
                                    @php $childCount = $count($child['badge']); @endphp
                                    <li>
                                        <a href="{{ $child['url'] }}" @class(['ui-nav-link vnd-nav-child', 'is-active' => $child['active']]) @if ($child['active']) aria-current="page" @endif>
                                            <span class="min-w-0 flex-1 truncate">{{ $child['label'] }}</span>
                                            @if ($childCount > 0)
                                                <span class="vnd-nav-count" data-nav-badge="{{ $child['badge'] }}">{{ $display($childCount) }}</span>
                                            @endif
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endforeach

        <div class="ui-sidebar-foot vnd-store-card-wrap">
            <div class="vnd-store-card">
                <div class="flex min-w-0 items-center gap-2">
                    <span class="vnd-store-avatar size-12">
                        @if ($logoUrl)
                            <img src="{{ $logoUrl }}" alt="" class="size-full bg-media object-contain" loading="lazy" width="48" height="48">
                        @else
                            {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr((string) $storeName, 0, 2)) }}
                        @endif
                    </span>
                    <span class="ui-sidebar-user-text grid min-w-0 leading-normal">
                        <span class="truncate text-base font-semibold text-ink">{{ $storeName }}</span>
                        <span class="truncate text-xs text-ink-secondary">{{ __('platform::vendor.workspace') }}</span>
                    </span>
                </div>
                @if ($logoutUrl)
                    <form method="POST" action="{{ $logoutUrl }}" class="ui-sidebar-user-text">
                        @csrf
                        <button type="submit" class="vnd-square size-9 text-[#5e5e5e] hover:bg-surface hover:text-ink dark:text-ink-secondary" aria-label="{{ __('identity::auth.logout') }}" title="{{ __('identity::auth.logout') }}">
                            <x-ui::icon name="tp-logout" :size="24" data-flip />
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </nav>
</aside>
<div class="ui-sidebar-scrim" data-sidebar-scrim aria-hidden="true"></div>
