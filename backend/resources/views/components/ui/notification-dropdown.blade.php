{{--
    Notification dropdown (Figma 32:10542), UI only: data arrives with the Notifications
    module (database + Reverb). 380 wide; header with unread count + "Mark all read";
    rows: 36px tone chip, title, meta, time; unread rows tinted with a dot; max 6 rows
    then it scrolls; footer "View all". Optional links render only when given.
    Live: with realtime (Reverb channel config) resources/js/panels/notification-live.js
    prepends pushed rows and bumps the count; latest-url re-reads the feed after actions.
    trigger-icon / trigger-icon-size swap the bell glyph (vendor topbar uses the client tp-bell).
    :items="[['title' => …, 'meta' => …, 'time' => '2m', 'icon' => 'cart', 'tone' => 'brand', 'unread' => true, 'url' => …]]"
--}}
@props(['items' => [], 'unread' => null, 'markAllUrl' => null, 'viewAllUrl' => null, 'latestUrl' => null, 'realtime' => null, 'triggerIcon' => 'bell', 'triggerIconSize' => 20])
@php
    $unread ??= collect($items)->where('unread', true)->count();
    $tones = ['brand' => 'bg-primary-subtle text-ink-brand', 'success' => 'bg-success-bg text-success', 'warning' => 'bg-warning-bg text-warning', 'danger' => 'bg-danger-bg text-danger', 'info' => 'bg-info-bg text-info'];
@endphp
<x-ui::dropdown align="end" width="min(380px, calc(100vw - 32px))" :label="__('ui.notifications.title')" data-notification-bell :data-latest-url="$latestUrl" {{ $attributes }}>
    <x-slot:trigger>
        <button type="button" data-dropdown-trigger aria-haspopup="menu" aria-expanded="false" class="relative grid size-10 place-items-center rounded-control text-ink-secondary hover:bg-subtle hover:text-ink" aria-label="{{ trans_choice('ui.notifications.bell', $unread, ['count' => $unread]) }}" title="{{ __('ui.notifications.title') }}">
            <x-ui::icon :name="$triggerIcon" :size="$triggerIconSize" />
            <span data-notification-count class="absolute end-1 top-1 grid h-4 min-w-4 place-items-center rounded-full bg-primary px-1 text-[11px] font-semibold leading-none text-on-primary ring-2 ring-surface" @if ($unread === 0) hidden @endif>{{ $unread > 99 ? '99+' : $unread }}</span>
        </button>
    </x-slot:trigger>

    <div class="-m-2">
        <header class="flex items-center gap-2 border-b border-line px-4 py-3">
            <h2 class="text-sm font-semibold text-ink">{{ __('ui.notifications.title') }}</h2>
            <span data-notification-new @if ($unread === 0) hidden @endif>
                <x-ui::badge tone="brand"><span data-notification-new-label>{{ trans_choice('ui.notifications.new', $unread, ['count' => $unread]) }}</span></x-ui::badge>
            </span>
            @if ($markAllUrl)
                <form method="POST" action="{{ $markAllUrl }}" class="ms-auto" data-ajax data-notification-mark-all @if ($unread === 0) hidden @endif>
                    @csrf
                    <button type="submit" class="text-xs font-medium text-ink-brand hover:underline">{{ __('ui.notifications.mark_all') }}</button>
                </form>
            @endif
        </header>
        <div data-notification-empty @if (count($items) > 0) hidden @endif>
            <x-ui::empty-state compact icon="bell" :title="__('ui.notifications.empty_title')" :description="__('ui.notifications.empty_description')" />
        </div>
            <ul class="max-h-[336px] overflow-y-auto" role="list" data-notification-list @if (count($items) === 0) hidden @endif>
                @foreach ($items as $item)
                    <li @isset($item['id']) data-notification-id="{{ $item['id'] }}" @endisset>
                        <a href="{{ $item['url'] ?? '#' }}" role="menuitem" tabindex="-1" @class(['flex gap-3 border-b border-line px-4 py-3 hover:bg-subtle focus-visible:bg-subtle', 'bg-primary-subtle/60' => $item['unread'] ?? false])>
                            <span class="grid size-9 shrink-0 place-items-center rounded-full {{ $tones[$item['tone'] ?? 'brand'] ?? $tones['brand'] }}">
                                <x-ui::icon :name="$item['icon'] ?? 'bell'" :size="18" />
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-[13px] font-medium text-ink">{{ $item['title'] }}</span>
                                @if (! empty($item['meta']))
                                    <span class="block truncate text-xs text-ink-secondary">{{ $item['meta'] }}</span>
                                @endif
                            </span>
                            <span class="flex flex-col items-end gap-1.5">
                                <span class="text-xs text-ink-muted">{{ $item['time'] ?? '' }}</span>
                                @if ($item['unread'] ?? false)
                                    <span class="size-1.5 rounded-full bg-primary" role="img" aria-label="{{ __('ui.notifications.unread') }}"></span>
                                @endif
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        <template data-notification-template>
            <li>
                <a href="#" role="menuitem" tabindex="-1" class="flex gap-3 border-b border-line bg-primary-subtle/60 px-4 py-3 hover:bg-subtle focus-visible:bg-subtle">
                    <span data-tone class="grid size-9 shrink-0 place-items-center rounded-full {{ $tones['brand'] }}">
                        <x-ui::icon name="bell" :size="18" />
                    </span>
                    <span class="min-w-0 flex-1">
                        <span data-title class="block truncate text-[13px] font-medium text-ink"></span>
                        <span data-meta class="block truncate text-xs text-ink-secondary"></span>
                    </span>
                    <span class="flex flex-col items-end gap-1.5">
                        <span data-time class="text-xs text-ink-muted"></span>
                        <span class="size-1.5 rounded-full bg-primary" role="img" aria-label="{{ __('ui.notifications.unread') }}"></span>
                    </span>
                </a>
            </li>
        </template>
        @if ($realtime)
            @php($realtimeConfig = $realtime + ['tones' => $tones, 'newLabel' => trans_choice('ui.notifications.new', 2, ['count' => ':count'])])
            <script type="application/json" data-notification-realtime>@json($realtimeConfig)</script>
        @endif
        @if ($viewAllUrl)
            <a href="{{ $viewAllUrl }}" class="block rounded-b-control bg-subtle py-2.5 text-center text-xs font-medium text-ink-brand hover:underline">{{ __('ui.notifications.view_all') }}</a>
        @endif
    </div>
</x-ui::dropdown>
