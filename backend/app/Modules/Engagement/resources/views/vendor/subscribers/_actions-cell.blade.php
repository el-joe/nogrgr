{{-- Row actions of VEN-026: open the linked customer (customers.view); with subscribers.manage unsubscribe / re-subscribe and delete. --}}
<div class="flex items-center justify-end gap-1">
    @if ($customerUrl)
        <x-ui::icon-button icon="eye" size="sm" :label="__('engagement::subscribers.view_customer', ['email' => $subscriber->email])" :href="$customerUrl" />
    @endif
    @if ($canManage)
        @php $subscribed = $subscriber->unsubscribed_at === null; @endphp
        <x-ui::row-actions>
            <x-ui::dropdown-item :icon="$subscribed ? 'x-circle' : 'check-circle'" :data-confirm="__($subscribed ? 'engagement::subscribers.unsubscribe_message' : 'engagement::subscribers.resubscribe_message')" :data-confirm-title="$subscriber->email" data-confirm-tone="primary" :data-confirm-label="__($subscribed ? 'engagement::subscribers.unsubscribe' : 'engagement::subscribers.resubscribe')" data-confirm-method="PATCH" :data-confirm-url="vendor_route('sales.subscribers.status', ['subscriber' => $subscriber->id, 'subscribed' => $subscribed ? 0 : 1])">{{ __($subscribed ? 'engagement::subscribers.unsubscribe' : 'engagement::subscribers.resubscribe') }}</x-ui::dropdown-item>
            <x-ui::dropdown-item icon="trash" tone="danger" :data-confirm="__('engagement::subscribers.delete_message')" :data-confirm-title="__('engagement::subscribers.delete_title', ['email' => $subscriber->email])" data-confirm-method="DELETE" :data-confirm-url="vendor_route('sales.subscribers.destroy', ['subscriber' => $subscriber->id])">{{ __('engagement::subscribers.delete') }}</x-ui::dropdown-item>
        </x-ui::row-actions>
    @elseif (! $customerUrl)
        <span class="text-xs text-ink-muted">—</span>
    @endif
</div>
