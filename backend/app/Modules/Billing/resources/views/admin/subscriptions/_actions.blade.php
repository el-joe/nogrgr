@php
    $canManage = (bool) auth('admin')->user()?->can('subscriptions.manage');
    $canInvoices = (bool) auth('admin')->user()?->can('finance.invoices.view');
    $invoice = $subscription->latestInvoice;
@endphp
<div class="flex items-center justify-end gap-1">
    @if ($invoice && $canInvoices)
        <x-ui::icon-button icon="file" size="sm" :href="route('admin.finance.invoices.show', $invoice->id)" :label="__('billing::subscriptions.view_invoice', ['number' => $invoice->number])" />
    @endif
    @if ($canManage && ($subscription->status->canTransitionTo(\App\Modules\Billing\Enums\SubscriptionStatus::Active) || $subscription->status->canTransitionTo(\App\Modules\Billing\Enums\SubscriptionStatus::Cancelled)))
        <x-ui::row-actions>
            @if ($subscription->status->canTransitionTo(\App\Modules\Billing\Enums\SubscriptionStatus::Active))
                <x-ui::dropdown-item
                    icon="refresh"
                    :data-confirm="__('billing::subscriptions.confirm_renew_message', ['amount' => $subscription->amount()->format()])"
                    :data-confirm-title="__('billing::subscriptions.confirm_renew_title', ['store' => $subscription->tenant?->getAttribute('name')])"
                    data-confirm-tone="primary"
                    data-confirm-method="POST"
                    :data-confirm-url="route('admin.billing.subscriptions.renew', $subscription->id)"
                >{{ __('billing::subscriptions.renew') }}</x-ui::dropdown-item>
            @endif
            @if ($subscription->status->canTransitionTo(\App\Modules\Billing\Enums\SubscriptionStatus::Cancelled))
                <x-ui::dropdown-divider />
                <x-ui::dropdown-item
                    icon="x-circle"
                    tone="danger"
                    :data-confirm="__('billing::subscriptions.confirm_cancel_message')"
                    :data-confirm-title="__('billing::subscriptions.confirm_cancel_title', ['store' => $subscription->tenant?->getAttribute('name')])"
                    data-confirm-method="POST"
                    :data-confirm-url="route('admin.billing.subscriptions.cancel', $subscription->id)"
                >{{ __('billing::subscriptions.cancel') }}</x-ui::dropdown-item>
            @endif
        </x-ui::row-actions>
    @endif
</div>
