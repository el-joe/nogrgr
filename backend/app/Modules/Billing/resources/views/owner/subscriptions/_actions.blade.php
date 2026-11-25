{{-- Subscription row: renew (hosted checkout) and the store's latest invoice. --}}
@php
    $renewable = $subscription->status->isOpen() && $subscription->amount_minor > 0;
    $store = (string) $subscription->tenant?->getAttribute('name');
@endphp
<div class="flex items-center justify-end gap-1">
    @if ($subscription->latestInvoice)
        <x-ui::icon-button icon="eye" size="sm" :href="route('owner.invoices.show', $subscription->latestInvoice->id)" :label="__('billing::owner.subscriptions.view_invoice', ['store' => $store])" />
    @endif
    @if ($renewable)
        <x-ui::button size="sm" variant="secondary" icon="refresh"
            data-renew-open
            :data-renew-url="route('owner.subscriptions.renew', $subscription->id)"
            :data-renew-store="$store"
            :data-renew-plan="(string) $subscription->plan?->name.' · '.$subscription->term->label()"
            :data-renew-amount="$subscription->amount()->format()"
        >{{ __('billing::owner.subscriptions.renew') }}</x-ui::button>
    @endif
</div>
