{{-- Invoice detail page (VEN-061a) — the side-panel fragment on its own page. --}}
<x-vendor::layout :title="$invoice->number" page="vendor-invoices">
    <div class="grid gap-6">
        <x-ui::page-header
            :title="$invoice->number"
            :breadcrumbs="[__('billing::vendor_invoices.crumb') => null, __('billing::vendor_invoices.title') => vendor_route('finance.invoices.index'), $invoice->number => null]"
        >
            <x-slot:actions>
                <x-ui::button variant="secondary" icon="chevron-left" :href="vendor_route('finance.invoices.index')" class="rtl:[&_svg]:rotate-180">{{ __('billing::vendor_invoices.actions.back') }}</x-ui::button>
            </x-slot:actions>
        </x-ui::page-header>
        <x-ui::card class="mx-auto w-full max-w-[720px]">
            @include('billing::vendor.invoices._detail', ['invoice' => $invoice, 'canPay' => $canPay])
        </x-ui::card>
    </div>

    @if ($canPay)
        @include('billing::vendor.invoices._pay-modal', ['gateways' => $gateways])
    @endif
</x-vendor::layout>
