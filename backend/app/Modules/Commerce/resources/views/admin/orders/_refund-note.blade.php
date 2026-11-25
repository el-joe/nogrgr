@if ($preview)
    <p class="flex items-start gap-2 rounded-control bg-warning-bg px-3 py-2 text-sm text-warning" role="note">
        <x-ui::icon name="alert-triangle" :size="16" class="mt-0.5 shrink-0" />
        <span>{{ __('commerce::orders_admin.cancel.refund_note', ['amount' => $preview->amount->format(), 'gateway' => \App\Modules\Commerce\DataTables\VendorOrdersDataTable::gatewayLabel($order->payment_gateway)]) }}</span>
    </p>
@else
    <p class="flex items-start gap-2 rounded-control bg-subtle px-3 py-2 text-sm text-ink-secondary" role="note">
        <x-ui::icon name="info" :size="16" class="mt-0.5 shrink-0" />
        <span>{{ __('commerce::orders_admin.cancel.no_refund') }}</span>
    </p>
@endif
