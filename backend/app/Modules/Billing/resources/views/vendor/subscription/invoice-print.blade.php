{{--
    Vendor › Subscription & balance › invoice print view (VEN-060c download / print):
    the shared print-optimised invoice sheet (resources/css/panels/print.css); the print
    dialog opens on load ([data-autoprint], vendor-orders page module).
--}}
<x-vendor::layout :title="$invoice->number" page="vendor-orders">
    <div class="grid gap-6" data-autoprint>
        <div class="flex flex-wrap items-center justify-between gap-3" data-print-hide>
            <x-ui::button variant="secondary" icon="chevron-left" :href="vendor_route('finance.subscription', ['tab' => 'history'])" class="rtl:[&_svg]:rotate-180">{{ __('billing::vendor_subscription.actions.back') }}</x-ui::button>
            <x-ui::button icon="printer" data-print>{{ __('billing::invoices.print') }}</x-ui::button>
        </div>

        <article class="mx-auto w-full max-w-[840px] rounded-card border border-line bg-surface p-8 shadow-card print:max-w-none print:border-0 print:p-0 print:shadow-none" data-print-sheet>
            <div class="mb-6 flex items-center gap-3">
                <h1 class="text-2xl font-semibold text-ink" dir="ltr">{{ $invoice->number }}</h1>
                @include('billing::admin._status', ['status' => $invoice->status])
            </div>
            @include('billing::admin.invoices._document', ['invoice' => $invoice])
        </article>
    </div>
</x-vendor::layout>
