{{-- Pay an unpaid invoice (VEN-061): gateway choice → the existing invoice checkout (finance.subscription.checkout, action invoice). --}}
<x-ui::modal id="invoice-pay-modal" :title="__('billing::vendor_subscription.invoice.title')" :subtitle="__('billing::vendor_subscription.invoice.subtitle')" size="sm">
    @if ($gateways->isEmpty())
        <x-ui::empty-state compact variant="error" icon="credit-card" :title="__('billing::vendor_subscription.no_gateways_title')" :description="__('billing::vendor_subscription.no_gateways')" />
    @else
        <form id="invoice-pay-form" method="POST" action="{{ vendor_route('finance.subscription.checkout') }}" class="grid gap-5" data-checkout-form novalidate>
            @csrf
            <input type="hidden" name="action" value="invoice">
            <input type="hidden" name="invoice_id" value="" data-invoice-id-input>
            <dl class="grid grid-cols-[auto_1fr] gap-x-6 gap-y-2 rounded-control bg-subtle px-4 py-3 text-sm">
                <dt class="text-ink-secondary">{{ __('billing::vendor_subscription.invoice.number') }}</dt>
                <dd class="text-end font-medium text-ink" dir="ltr" data-invoice-number-label></dd>
                <dt class="text-ink-secondary">{{ __('billing::vendor_subscription.invoice.amount') }}</dt>
                <dd class="text-end font-semibold text-ink"><bdi dir="ltr" data-invoice-amount-label></bdi></dd>
            </dl>
            @include('billing::vendor.subscription._gateways', ['gateways' => $gateways, 'prefix' => 'invoice'])
        </form>
    @endif
    <x-slot:footer>
        <x-ui::button variant="secondary" data-overlay-close>{{ __('ui.cancel') }}</x-ui::button>
        @if ($gateways->isNotEmpty())
            <x-ui::button type="submit" form="invoice-pay-form" icon="credit-card">{{ __('billing::vendor_subscription.continue_payment') }}</x-ui::button>
        @endif
    </x-slot:footer>
</x-ui::modal>
