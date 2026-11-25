{{--
    Vendor side menu (Tenant Panel Layout, Figma 12:25829): 236 wide, logo, 48px items
    with the 8px start rail; the active item is the orange pill that runs into the
    inline-end edge. Footer: store card (logo / initials, name, "Vendor Workspace",
    sign out). Collapse / drawer behaviour comes from resources/js/panels/ui/sidebar.js.
--}}
@props(['sections' => [], 'counts' => [], 'homeUrl' => null, 'storeName' => null, 'logoUrl' => null])
@php
    $count = fn (?string $key) => $key !== null ? (int) ($counts[$key] ?? 0) : 0;
    $display = fn (int $value) => $value > 99 ? '99+' : (string) $value;
    $logoutUrl = app(\App\Shared\Navigation\PanelShell::class)->url('vendor', 'logout');
@endphp
<aside id="app-sidebar" class="ui-sidebar vnd-sidebar" data-sidebar-root aria-label="{{ __('ui.sidebar.label') }}">
    <div class="ui-sidebar-head flex h-[88px] shrink-0 items-center justify-center gap-2 px-5">
        @if ($homeUrl)
            <a href="{{ $homeUrl }}" class="rounded-control" aria-label="{{ __('ui.sidebar.home') }}"><x-ui::logo variant="light" :tag="__('ui.tags.vendor')" /></a>
        @else
            <x-ui::logo variant="light" :tag="__('ui.tags.vendor')" />
        @endif
        <button type="button" data-sidebar-toggle class="ms-auto grid size-9 place-items-center rounded-control text-sidebar-ink hover:bg-sidebar-hover lg:hidden" aria-controls="app-sidebar" aria-label="{{ __('ui.sidebar.close_menu') }}" title="{{ __('ui.sidebar.close_menu') }}">
            <x-ui::icon name="x" :size="20" />
        </button>
    </div>

    <nav class="ui-sidebar-nav min-h-0 flex-1 overflow-y-auto overflow-x-hidden pb-4" aria-label="{{ __('ui.sidebar.main') }}">
        @foreach ($sections as $section)
            <ul class="grid gap-0.5" role="list" @if ($section['label']) aria-label="{{ $section['label'] }}" @endif>
                @foreach ($section['items'] as $item)
                    @php $itemCount = $count($item['badge']); @endphp
                    @if ($item['children'] === [])
                        <li>
                            <a href="{{ $item['url'] }}" @class(['vnd-nav-link group/link', 'is-active' => $item['active']]) @if ($item['active']) aria-current="page" @endif title="{{ $item['label'] }}">
                                <span class="vnd-nav-rail" aria-hidden="true"></span>
                                <span class="vnd-nav-pill">
                                    @if ($item['icon'])<x-ui::icon :name="$item['icon']" :size="24" />@endif
                                    <span class="ui-nav-label min-w-0 flex-1 truncate">{{ $item['label'] }}</span>
                                    @if ($itemCount > 0)
                                        <span class="ui-nav-count vnd-nav-count" data-nav-badge="{{ $item['badge'] }}">{{ $display($itemCount) }}</span>
                                    @endif
                                </span>
                            </a>
                        </li>
                    @else
                        @php $groupId = 'nav-group-'.\Illuminate\Support\Str::slug($item['key']); @endphp
                        <li class="ui-nav-group" data-nav-group="{{ $item['key'] }}" @if ($item['open']) data-open @endif>
                            <button type="button" data-nav-group-toggle aria-expanded="{{ $item['open'] ? 'true' : 'false' }}" aria-controls="{{ $groupId }}" @class(['vnd-nav-link w-full text-start', 'is-active' => $item['active']]) title="{{ $item['label'] }}">
                                <span class="vnd-nav-rail" aria-hidden="true"></span>
                                <span class="vnd-nav-pill">
                                    @if ($item['icon'])<x-ui::icon :name="$item['icon']" :size="24" />@endif
                                    <span class="ui-nav-label min-w-0 flex-1 truncate">{{ $item['label'] }}</span>
                                    @if ($itemCount > 0)
                                        <span class="ui-nav-count vnd-nav-count">{{ $display($itemCount) }}</span>
                                    @endif
                                    <x-ui::icon name="chevron-down" :size="16" class="ui-nav-chevron" />
                                </span>
                            </button>
                            <ul id="{{ $groupId }}" class="ui-nav-children vnd-nav-children" role="list" aria-label="{{ $item['label'] }}">
                                @foreach ($item['children'] as $child)
                                    <li>
                                        <a href="{{ $child['url'] }}" @class(['vnd-nav-child', 'is-active' => $child['active']]) @if ($child['active']) aria-current="page" @endif>
                                            <span class="size-1.5 shrink-0 rounded-full bg-current opacity-60" aria-hidden="true"></span>
                                            <span class="min-w-0 flex-1 truncate">{{ $child['label'] }}</span>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </li>
                    @endif
                @endforeach
            </ul>
        @endforeach
    </nav>

    <div class="ui-sidebar-foot shrink-0 px-2 pb-4">
        <div class="flex items-center justify-between gap-2 bg-subtle px-3 py-4">
            <div class="flex min-w-0 items-center gap-2">
                <span class="grid size-12 shrink-0 place-items-center overflow-hidden rounded-full bg-inverse text-sm font-semibold text-on-inverse">
                    @if ($logoUrl)
                        <img src="{{ $logoUrl }}" alt="" class="size-full object-cover" loading="lazy" width="48" height="48">
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
                    <button type="submit" class="grid size-9 place-items-center rounded-control text-ink-secondary transition-colors hover:bg-surface hover:text-ink" aria-label="{{ __('identity::auth.logout') }}" title="{{ __('identity::auth.logout') }}">
                        <x-ui::icon name="log-out" :size="22" data-flip />
                    </button>
                </form>
            @endif
        </div>
    </div>
</aside>
<div class="ui-sidebar-scrim" data-sidebar-scrim aria-hidden="true"></div>
