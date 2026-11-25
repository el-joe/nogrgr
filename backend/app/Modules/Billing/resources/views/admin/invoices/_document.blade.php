{{-- Invoice body shared by the detail page and the print view (Figma 74:65309). --}}
@php
    $seller = config('billing.seller');
    $date = fn ($value) => $value?->translatedFormat('j M Y');
@endphp
<div class="grid gap-6">
    <div class="flex flex-wrap items-start justify-between gap-6">
        <div class="grid gap-2">
            <x-ui::logo class="h-8" />
            <p class="text-sm text-ink-secondary">
                {{ $seller['name'] }}@if ($seller['address']) · {{ $seller['address'] }}@endif
                @if ($seller['tax_number']) · {{ __('billing::invoices.tax_number', ['number' => $seller['tax_number']]) }}@endif
            </p>
            <p class="text-sm text-ink">
                {{ __('billing::invoices.bill_to') }}:
                {{ $invoice->bill_to_name ?? $invoice->tenant?->getAttribute('name') }}@if ($invoice->bill_to_email) · <bdi dir="ltr">{{ $invoice->bill_to_email }}</bdi>@endif
            </p>
        </div>
        <dl class="grid min-w-64 grid-cols-[auto_1fr] gap-x-8 gap-y-2 text-sm">
            <dt class="text-ink-secondary">{{ __('billing::invoices.columns.invoice') }}</dt>
            <dd class="text-end font-medium text-ink" dir="ltr">{{ $invoice->number }}</dd>
            <dt class="text-ink-secondary">{{ __('billing::invoices.columns.issued') }}</dt>
            <dd class="text-end text-ink">{{ $date($invoice->issued_at) ?? '—' }}</dd>
            @if ($invoice->paid_at)
                <dt class="text-ink-secondary">{{ __('billing::invoices.paid') }}</dt>
                <dd class="text-end text-ink">{{ $date($invoice->paid_at) }}@if ($invoice->payment_method) · {{ \App\Modules\Billing\Enums\PaymentGateway::tryFrom($invoice->payment_method)?->label() ?? $invoice->payment_method }}@endif</dd>
            @elseif ($invoice->due_at)
                <dt class="text-ink-secondary">{{ __('billing::invoices.due_date') }}</dt>
                <dd class="text-end text-ink">{{ $date($invoice->due_at) }}</dd>
            @endif
        </dl>
    </div>

    <div class="relative overflow-x-auto rounded-card border border-line">
        <table class="w-full text-sm">
            <thead class="bg-subtle text-xs text-ink-secondary">
                <tr>
                    <th scope="col" class="px-4 py-3 text-start font-medium">{{ __('billing::invoices.columns.description') }}</th>
                    <th scope="col" class="px-4 py-3 text-end font-medium">{{ __('billing::invoices.columns.qty') }}</th>
                    <th scope="col" class="px-4 py-3 text-end font-medium">{{ __('billing::invoices.columns.unit') }}</th>
                    <th scope="col" class="px-4 py-3 text-end font-medium">{{ __('billing::invoices.columns.total') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($invoice->items as $item)
                    <tr class="border-t border-line">
                        <td class="px-4 py-3 text-ink">{{ $item->description }}</td>
                        <td class="px-4 py-3 text-end text-ink">{{ $item->quantity }}</td>
                        <td class="px-4 py-3 text-end text-ink"><bdi dir="ltr">{{ $item->unitAmount($invoice->currency)->format() }}</bdi></td>
                        <td class="px-4 py-3 text-end text-ink"><bdi dir="ltr">{{ $item->total($invoice->currency)->format() }}</bdi></td>
                    </tr>
                @empty
                    <tr class="border-t border-line">
                        <td colspan="4" class="px-4 py-6 text-center text-ink-secondary">{{ __('billing::invoices.no_lines') }}</td>
                    </tr>
                @endforelse
                @if ($invoice->tax_rate_bp > 0)
                    <tr class="border-t border-line">
                        <td colspan="3" class="px-4 py-3 text-ink">{{ __('billing::invoices.tax_line', ['rate' => \Illuminate\Support\Number::format($invoice->taxPercent(), maxPrecision: 2, locale: \App\Shared\Support\Locale::numberLocale())]) }}</td>
                        <td class="px-4 py-3 text-end text-ink"><bdi dir="ltr">{{ $invoice->tax()->format() }}</bdi></td>
                    </tr>
                @endif
            </tbody>
        </table>
    </div>

    <div class="flex items-center justify-between gap-4 text-sm">
        <span class="text-ink-secondary">{{ $invoice->status === \App\Modules\Billing\Enums\InvoiceStatus::Paid ? __('billing::invoices.total_paid', ['currency' => $invoice->currency]) : __('billing::invoices.total_due', ['currency' => $invoice->currency]) }}</span>
        <span class="text-base font-semibold text-ink"><bdi dir="ltr">{{ $invoice->total()->format() }}</bdi></span>
    </div>
</div>
