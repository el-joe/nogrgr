{{--
    Panel sidebar (Figma Sidebar 37:12436, items 35:11405, sections 35:11406).
    Expanded 264 / collapsed 76 (icon-only, remembered) / off-canvas drawer under 1024.
    Brand header 64 + scrolling nav + user footer with the collapse chevron.
    :sections come from App\Shared\Navigation\NavigationBuilder (routes that do not exist
    and items without permission are already removed). :counts = ['orders' => 8, …].
--}}
@props(['sections' => [], 'counts' => [], 'user' => null, 'role' => null, 'tag' => null, 'homeUrl' => null])
@php
    $count = fn (?string $key) => $key !== null ? (int) ($counts[$key] ?? 0) : 0;
    $display = fn (int $value) => $value > 99 ? '99+' : (string) $value;
@endphp
<aside id="app-sidebar" class="ui-sidebar" data-sidebar-root aria-label="{{ __('ui.sidebar.label') }}">
    <div class="ui-sidebar-head flex h-16 shrink-0 items-center justify-between gap-2 px-5">
        @if ($homeUrl)
            <a href="{{ $homeUrl }}" class="rounded-control" aria-label="{{ __('ui.sidebar.home') }}"><x-ui::logo :tag="$tag" /></a>
        @else
            <x-ui::logo :tag="$tag" />
        @endif
        <button type="button" data-sidebar-toggle class="grid size-9 place-items-center rounded-control text-sidebar-ink hover:bg-sidebar-hover lg:hidden" aria-controls="app-sidebar" aria-label="{{ __('ui.sidebar.close_menu') }}" title="{{ __('ui.sidebar.close_menu') }}">
            <x-ui::icon name="x" :size="20" />
        </button>
    </div>

    <nav class="ui-sidebar-nav min-h-0 flex-1 overflow-y-auto overflow-x-hidden px-4 pb-4" aria-label="{{ __('ui.sidebar.main') }}">
        @foreach ($sections as $section)
            @if ($section['label'])
                <p class="ui-nav-section-label flex h-8 items-end px-3 pb-1 text-[11px] font-semibold uppercase leading-4 tracking-[0.06em] text-sidebar-muted">{{ $section['label'] }}</p>
                <div class="ui-nav-section-divider mx-auto my-2 hidden h-px w-11 bg-sidebar-line" aria-hidden="true"></div>
            @endif
            <ul class="grid gap-0.5" role="list">
                @foreach ($section['items'] as $item)
                    @php
                        $itemCount = $count($item['badge']);
                        $pill = 'bg-sidebar-active text-sidebar-ink-active font-medium';
                        $linkBase = 'ui-nav-link group/link relative flex h-10 w-full items-center gap-3 rounded-control px-3 text-start text-sm transition-colors';
                    @endphp
                    @if ($item['children'] === [])
                        <li>
                            <a href="{{ $item['url'] }}" @class([$linkBase, $pill => $item['active'], 'text-sidebar-ink hover:bg-sidebar-hover hover:text-sidebar-ink-active' => ! $item['active']]) @if ($item['active']) aria-current="page" @endif title="{{ $item['label'] }}">
                                @if ($item['icon'])<x-ui::icon :name="$item['icon']" :size="20" />@endif
                                <span class="ui-nav-label min-w-0 flex-1 truncate">{{ $item['label'] }}</span>
                                @if ($itemCount > 0)
                                    <span @class(['ui-nav-count inline-flex h-4 min-w-5 items-center justify-center rounded-full px-1.5 text-xs font-medium leading-4', 'bg-surface text-ink-brand' => $item['active'], 'bg-primary text-on-primary' => ! $item['active']])>{{ $display($itemCount) }}</span>
                                    <span class="ui-nav-dot absolute end-1.5 top-1.5 hidden size-2 rounded-full bg-primary ring-2 ring-sidebar" aria-hidden="true"></span>
                                @endif
                            </a>
                        </li>
                    @else
                        @php
                            $groupId = 'nav-group-'.\Illuminate\Support\Str::slug($item['key']);
                            $groupCount = $itemCount;
                        @endphp
                        <li class="ui-nav-group" data-nav-group="{{ $item['key'] }}" @if ($item['open']) data-open @endif>
                            <button type="button" data-nav-group-toggle aria-expanded="{{ $item['open'] ? 'true' : 'false' }}" aria-controls="{{ $groupId }}" @class([$linkBase, $pill => $item['active'], 'text-sidebar-ink hover:bg-sidebar-hover hover:text-sidebar-ink-active' => ! $item['active']]) title="{{ $item['label'] }}">
                                @if ($item['icon'])<x-ui::icon :name="$item['icon']" :size="20" />@endif
                                <span class="ui-nav-label min-w-0 flex-1 truncate">{{ $item['label'] }}</span>
                                @if ($groupCount > 0)
                                    <span @class(['ui-nav-count inline-flex h-4 min-w-5 items-center justify-center rounded-full px-1.5 text-xs font-medium leading-4', 'bg-surface text-ink-brand' => $item['active'], 'bg-primary text-on-primary' => ! $item['active']])>{{ $display($groupCount) }}</span>
                                    <span class="ui-nav-dot absolute end-1.5 top-1.5 hidden size-2 rounded-full bg-primary ring-2 ring-sidebar" aria-hidden="true"></span>
                                @endif
                                <x-ui::icon name="chevron-down" :size="16" @class(['ui-nav-chevron', 'text-sidebar-ink-active' => $item['active'], 'text-sidebar-muted' => ! $item['active']]) />
                            </button>
                            <ul id="{{ $groupId }}" class="ui-nav-children pt-0.5" role="list" aria-label="{{ $item['label'] }}">
                                @foreach ($item['children'] as $child)
                                    <li>
                                        <a href="{{ $child['url'] }}" @class([
                                            'ui-nav-link flex h-9 items-center gap-3 rounded-control pe-3 ps-11 text-[13px] leading-[18px] transition-colors',
                                            'bg-sidebar-hover font-medium text-sidebar-ink-active' => $child['active'],
                                            'text-sidebar-ink hover:bg-sidebar-hover hover:text-sidebar-ink-active' => ! $child['active'],
                                        ]) @if ($child['active']) aria-current="page" @endif>
                                            <span @class(['size-1.5 shrink-0 rounded-full', 'bg-primary' => $child['active'], 'bg-sidebar-muted' => ! $child['active']]) aria-hidden="true"></span>
                                            <span class="min-w-0 flex-1 truncate">{{ $child['label'] }}</span>
                                            @if ($count($child['badge']) > 0)
                                                <span class="inline-flex h-4 min-w-5 items-center justify-center rounded-full bg-primary px-1.5 text-xs font-medium leading-4 text-on-primary">{{ $display($count($child['badge'])) }}</span>
                                            @endif
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

    <div class="ui-sidebar-foot flex h-[72px] shrink-0 items-center gap-3 border-t border-sidebar-line px-4">
        <x-ui::avatar :name="$user?->name" tone="brand" :size="36" />
        <div class="ui-sidebar-user-text min-w-0 flex-1">
            <p class="truncate text-[13px] font-medium leading-[18px] text-sidebar-ink-active">{{ $user?->name }}</p>
            @if ($role)
                <p class="truncate text-xs text-sidebar-muted">{{ $role }}</p>
            @endif
        </div>
        <button type="button" data-sidebar-toggle class="ui-sidebar-collapse hidden size-8 place-items-center rounded-chip text-sidebar-muted hover:bg-sidebar-hover hover:text-sidebar-ink-active lg:grid" aria-controls="app-sidebar" aria-expanded="true" aria-label="{{ __('ui.sidebar.toggle') }}" title="{{ __('ui.sidebar.toggle') }}">
            <x-ui::icon name="chevron-left" :size="18" />
        </button>
    </div>
</aside>
<div class="ui-sidebar-scrim" data-sidebar-scrim aria-hidden="true"></div>
