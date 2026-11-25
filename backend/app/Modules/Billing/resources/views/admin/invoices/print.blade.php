{{--
    Print-optimised invoice (ADM-054a print / PDF): panel chrome, toasts and buttons are
    hidden by resources/css/panels/print.css, the print dialog opens automatically
    (resources/js/panels/admin/billing.js) and the page prints in light mode. "Save as
    PDF" in the dialog produces the PDF.
--}}
<x-layouts.panel surface="admin" :title="$invoice->number" data-page="billing" data-autoprint>
    <div class="flex flex-wrap items-center justify-between gap-3" data-print-hide>
        <x-ui::button variant="secondary" icon="chevron-left" :href="route('admin.finance.invoices.show', $invoice->id)" class="rtl:[&_svg]:rotate-180">{{ __('billing::invoices.back_to_invoice') }}</x-ui::button>
        <x-ui::button icon="printer" data-print>{{ __('billing::invoices.print') }}</x-ui::button>
    </div>

    <article class="mx-auto w-full max-w-[840px] rounded-card border border-line bg-surface p-8 shadow-card print:max-w-none print:border-0 print:p-0 print:shadow-none" data-print-sheet>
        <div class="mb-6 flex items-center gap-3">
            <h1 class="text-2xl font-semibold text-ink" dir="ltr">{{ $invoice->number }}</h1>
            @include('billing::admin._status', ['status' => $invoice->status])
        </div>
        @include('billing::admin.invoices._document', ['invoice' => $invoice])
    </article>
</x-layouts.panel>
