@php
    /** @var \App\Modules\Notifications\Models\PanelNotificationRecord $notification */
    $category = $notification->category();
    $unread = $notification->read_at === null;
    $tones = ['brand' => 'bg-primary-subtle text-ink-brand', 'success' => 'bg-success-bg text-success', 'warning' => 'bg-warning-bg text-warning', 'danger' => 'bg-danger-bg text-danger', 'info' => 'bg-info-bg text-info'];
@endphp
<a href="{{ route($surface.'.notifications.open', $notification->getKey()) }}" class="group flex min-w-0 items-center gap-3">
    <span class="grid size-9 shrink-0 place-items-center rounded-control {{ $tones[$category->tone()] ?? $tones['brand'] }}">
        <x-ui::icon :name="$category->icon()" :size="18" />
    </span>
    <span class="grid min-w-0">
        <span @class(['truncate text-ink group-hover:underline', 'font-semibold' => $unread, 'font-medium' => ! $unread])>{{ $notification->title() }}</span>
        <span class="truncate text-xs text-ink-secondary">{{ $category->label() }} · {{ $notification->body() }}</span>
    </span>
    @if ($unread)
        <x-ui::badge tone="brand" dot class="ms-auto shrink-0">{{ __('notifications::panel.inbox.new') }}</x-ui::badge>
    @endif
</a>
