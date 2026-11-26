{{--
    One owner inbox row (Figma 122:105109): category icon tile, title, body · date; unread
    rows are tinted with a "New" badge and "Mark as read"; the row opens its target.
--}}
@php
    /** @var \App\Modules\Notifications\Models\PanelNotificationRecord $notification */
    $category = $notification->category();
    $unread = $notification->read_at === null;
    $created = $notification->created_at?->locale(app()->getLocale());
@endphp
<div @class(['flex flex-wrap items-center gap-3 rounded-control px-3 py-3 sm:flex-nowrap', 'bg-primary-subtle' => $unread])>
    <a href="{{ route('owner.notifications.open', $notification->getKey()) }}" class="group flex min-w-0 flex-1 items-center gap-3">
        <span class="grid size-9 shrink-0 place-items-center rounded-control bg-subtle text-ink-secondary">
            <x-ui::icon :name="$category->icon()" :size="18" />
        </span>
        <span class="grid min-w-0">
            <span @class(['truncate text-sm text-ink group-hover:underline', 'font-semibold' => $unread, 'font-medium' => ! $unread])>{{ $notification->title() }}</span>
            <span class="truncate text-xs text-ink-secondary">
                {{ $notification->body() }}
                @if ($created)
                    · <time datetime="{{ $created->toIso8601String() }}" title="{{ $created->isoFormat('D MMM YYYY, HH:mm') }}">{{ $created->isoFormat('D MMM') }}</time>
                @endif
            </span>
        </span>
    </a>
    <div class="ms-auto flex shrink-0 items-center gap-2">
        @if ($unread)
            <x-ui::badge tone="danger" dot>{{ __('notifications::panel.inbox.new') }}</x-ui::badge>
            <x-ui::button size="sm" variant="ghost" data-notification-mark :data-url="route('owner.notifications.mark', $notification->getKey())" data-read="1">{{ __('notifications::panel.inbox.mark_read') }}</x-ui::button>
        @else
            <x-ui::button size="sm" variant="ghost" data-notification-mark :data-url="route('owner.notifications.mark', $notification->getKey())" data-read="0">{{ __('notifications::panel.inbox.mark_unread') }}</x-ui::button>
        @endif
    </div>
</div>
