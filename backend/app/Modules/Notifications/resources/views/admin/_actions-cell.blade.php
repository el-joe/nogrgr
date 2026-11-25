@php
    /** @var \App\Modules\Notifications\Models\PanelNotificationRecord $notification */
    $unread = $notification->read_at === null;
@endphp
<div class="flex items-center justify-end gap-1">
    <x-ui::icon-button icon="eye" size="sm" :label="__('notifications::panel.inbox.open')" :href="route($surface.'.notifications.open', $notification->getKey())" />
    <x-ui::row-actions>
        <x-ui::dropdown-item icon="check" data-notification-mark :data-url="route($surface.'.notifications.mark', $notification->getKey())" :data-read="$unread ? '1' : '0'">{{ $unread ? __('notifications::panel.inbox.mark_read') : __('notifications::panel.inbox.mark_unread') }}</x-ui::dropdown-item>
        <x-ui::dropdown-divider />
        <x-ui::dropdown-item icon="trash" tone="danger" :data-confirm="__('notifications::panel.inbox.delete_message')" :data-confirm-title="__('notifications::panel.inbox.delete_title')" data-confirm-method="DELETE" :data-confirm-url="route($surface.'.notifications.destroy', $notification->getKey())">{{ __('notifications::panel.inbox.delete') }}</x-ui::dropdown-item>
    </x-ui::row-actions>
</div>
