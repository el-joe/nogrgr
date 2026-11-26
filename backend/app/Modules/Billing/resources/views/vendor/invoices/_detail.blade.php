{{--
    Invoice detail (VEN-061a, Figma Vendor Panel 88:110393 right panel): number + status,
    PDF / Print, seller → store, lines with totals, VAT, total; Pay now when unpaid.
    Rendered in the invoices page side panel (ajax) and on the invoice page.
--}}
@php
    $seller = config('billing.seller.name') ?: config('app.name');
    $store = $invoice->bill_to_name ?? $invoice->tenant?->getAttribute('name');
    $date = fn ($value) => $value?->locale(app()->getLocale())->translatedFormat('j M Y');
@endphp
<div class="grid gap-4" data-invoice-detail="{{ $invoice->id }}">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-2">
            <h2 class="text-base font-semibold text-ink" dir="ltr">{{ $invoice->number }}</h2>
            @include('billing::admin._status', ['status' => $invoice->status])
        </div>
        <div class="flex items-center gap-2">
            <x-ui::button size="sm" variant="secondary" icon="download" :href="vendor_route('finance.invoices.print', ['invoice' => $invoice->id, 'save' => 1])" target="_blank" rel="noopener" :title="__('billing::vendor_invoices.detail.pdf_hint')">{{ __('billing::vendor_invoices.actions.pdf') }}</x-ui::button>
            <x-ui::button size="sm" variant="secondary" icon="printer" :href="vendor_route('finance.invoices.print', ['invoice' => $invoice->id])" target="_blank" rel="noopener">{{ __('billing::vendor_invoices.actions.print') }}</x-ui::button>
        </div>
    </div>
    <p class="text-sm text-ink">{{ __('billing::vendor_invoices.detail.from_to', ['seller' => $seller, 'store' => $store]) }}</p>
    <p class="text-xs text-ink-secondary">
        {{ __('billing::vendor_invoices.detail.issued', ['date' => $date($invoice->issued_at) ?? '—']) }}
        @if ($invoice->paid_at)
            · {{ __('billing::vendor_invoices.detail.paid', ['date' => $date($invoice->paid_at)]) }}
        @elseif ($invoice->due_at)
            · {{ __('billing::vendor_invoices.detail.due', ['date' => $date($invoice->due_at)]) }}
        @endif
        · {{ $invoice->type->label() }}
    </p>
    <div class="overflow-x-auto rounded-card border border-line">
        <table class="w-full text-sm">
            <thead class="bg-subtle text-xs text-ink-secondary">
                <tr>
                    <th scope="col" class="px-4 py-3 text-start font-medium">{{ __('billing::vendor_invoices.detail.item') }}</th>
                    <th scope="col" class="px-4 py-3 text-end font-medium">{{ __('billing::vendor_invoices.detail.total') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($invoice->items as $item)
                    <tr class="border-t border-line">
                        <td class="px-4 py-4 text-ink">{{ $item->description }}@if ($item->quantity > 1) <span class="text-ink-secondary" dir="ltr">× {{ $item->quantity }}</span>@endif</td>
                        <td class="px-4 py-4 text-end font-medium text-ink"><bdi dir="ltr">{{ $item->total($invoice->currency)->format() }}</bdi></td>
                    </tr>
                @empty
                    <tr class="border-t border-line">
                        <td colspan="2" class="px-4 py-6 text-center text-ink-secondary">{{ __('billing::invoices.no_lines') }}</td>
                    </tr>
                @endforelse
                @if ($invoice->tax_rate_bp > 0)
                    <tr class="border-t border-line">
                        <td class="px-4 py-4 text-ink">{{ __('billing::vendor_invoices.detail.tax', ['rate' => \Illuminate\Support\Number::format($invoice->taxPercent(), maxPrecision: 2, locale: \App\Shared\Support\Locale::numberLocale())]) }}</td>
                        <td class="px-4 py-4 text-end font-medium text-ink"><bdi dir="ltr">{{ $invoice->tax()->format() }}</bdi></td>
                    </tr>
                @endif
                <tr class="border-t border-line">
                    <td class="px-4 py-4 font-semibold text-ink">{{ __('billing::vendor_invoices.detail.total') }}</td>
                    <td class="px-4 py-4 text-end font-semibold text-ink"><bdi dir="ltr">{{ $invoice->total()->format() }}</bdi></td>
                </tr>
            </tbody>
        </table>
    </div>
    @if ($canPay && $invoice->status === \App\Modules\Billing\Enums\InvoiceStatus::Unpaid)
        <div class="flex justify-end">
            <x-ui::button icon="credit-card"
                data-invoice-pay
                :data-invoice-id="$invoice->id"
                :data-invoice-number="$invoice->number"
                :data-invoice-amount="$invoice->total()->format()"
            >{{ __('billing::vendor_invoices.actions.pay') }}</x-ui::button>
        </div>
    @endif
</div>
