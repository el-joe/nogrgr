{{--
    One inbox row (Figma 12:17282): title + "new" pill, body, time and, while unread, a
    "Mark as read" button; the row links to the notification target (open = mark read).
--}}
@php
    /** @var \App\Modules\Notifications\Models\PanelNotificationRecord $notification */
    $unread = $notification->read_at === null;
    $category = $notification->category();
    $created = $notification->created_at?->locale(app()->getLocale());
@endphp
<div @class(['flex flex-wrap items-center gap-3 rounded-card px-4 py-3 sm:flex-nowrap', 'bg-subtle' => $unread])>
    <a href="{{ vendor_route('notifications.open', ['notification' => $notification->getKey()]) }}" class="group grid min-w-0 flex-1 gap-1">
        <span class="flex flex-wrap items-center gap-2">
            <span @class(['text-[15px] text-ink group-hover:underline', 'font-semibold' => $unread, 'font-medium' => ! $unread])>{{ $notification->title() }}</span>
            @if ($unread)
                <x-ui::badge tone="warning">{{ __('notifications::panel.inbox.new') }}</x-ui::badge>
            @endif
        </span>
        <span class="text-sm text-ink-secondary">{{ $notification->body() }}</span>
        <span class="text-xs text-ink-secondary">{{ $category->label() }}</span>
    </a>
    <div class="ms-auto flex shrink-0 items-center gap-3">
        @if ($created)
            <time datetime="{{ $created->toIso8601String() }}" title="{{ $created->isoFormat('D MMM YYYY, HH:mm') }}" class="text-xs text-ink-secondary">{{ $created->diffForHumans() }}</time>
        @endif
        @if ($unread)
            <x-ui::button size="sm" variant="secondary" data-notification-mark :data-url="vendor_route('notifications.mark', ['notification' => $notification->getKey()])" data-read="1">{{ __('notifications::panel.inbox.mark_read') }}</x-ui::button>
        @else
            <x-ui::button size="sm" variant="ghost" data-notification-mark :data-url="vendor_route('notifications.mark', ['notification' => $notification->getKey()])" data-read="0">{{ __('notifications::panel.inbox.mark_unread') }}</x-ui::button>
        @endif
    </div>
</div>
