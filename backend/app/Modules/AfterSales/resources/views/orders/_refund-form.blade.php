{{-- ADM-021b / VEN-021b refund modal (Figma 70:41386 / 84:107236): amount ≤ refundable, reason, restock. --}}
<form id="order-refund-form" method="POST" action="{{ $action }}" data-ajax class="grid gap-4"
    data-modal-title="{{ __('after-sales::refunds.issue_title') }}" data-modal-subtitle="{{ __('after-sales::refunds.refundable', ['amount' => $remaining->format()]) }}">
    @csrf
    @if (! $remaining->isPositive())
        <x-ui::empty-state compact icon="check-circle" :title="__('after-sales::refunds.nothing_left')" />
    @else
        <p class="text-sm text-ink-secondary">{{ __('after-sales::refunds.refundable', ['amount' => $remaining->format()]) }} · {{ __('after-sales::refunds.to_original', ['gateway' => \App\Modules\Commerce\DataTables\VendorOrdersDataTable::gatewayLabel($order->payment_gateway)]) }}</p>
        <x-ui::input name="amount" type="number" step="0.01" min="0.01" :max="$remaining->toDecimal()" :label="__('after-sales::refunds.fields.amount')" :value="$remaining->toDecimal()" required dir="ltr" />
        <x-ui::input name="reason" :label="__('after-sales::refunds.fields.reason')" maxlength="255" required :placeholder="__('after-sales::refunds.reason_placeholder')" />
        @if ($canRestock)
            <x-ui::checkbox name="restock" :label="__('after-sales::refunds.fields.restock')" :description="__('after-sales::refunds.restock_hint')" />
        @endif
    @endif
    @include('commerce::partials.modal-actions', ['submit' => __('after-sales::refunds.issue'), 'icon' => 'return'])
</form>
