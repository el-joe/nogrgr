{{-- Settlement figures (ORDER_FORMULAS.md §2–3), identical in both panels: $figures (SettlementFigures), $order. --}}
@php $f = $figures; @endphp
<dl class="grid gap-2.5 text-sm">
    <div class="flex justify-between gap-4"><dt class="text-ink-secondary">{{ __('commerce::settlement.vendor_share', ['percent' => $f->vendorPercent]) }}</dt><dd class="font-semibold text-ink" dir="ltr">{{ $f->vendorShare->format() }}</dd></div>
    <div class="flex justify-between gap-4"><dt class="text-ink-secondary">{{ __('commerce::settlement.platform_share', ['percent' => $f->platformPercent]) }}</dt><dd class="font-semibold text-ink" dir="ltr">{{ $f->platformShare->format() }}</dd></div>
    @if ($order->coupon_discount > 0)
        <div class="flex justify-between gap-4"><dt class="text-ink-secondary">{{ __('commerce::settlement.coupon_funded') }}</dt><dd class="font-semibold text-ink">{{ $order->coupon_source?->label() ?? '—' }}</dd></div>
    @endif
    <div class="flex justify-between gap-4"><dt class="text-ink-secondary">{{ __('commerce::settlement.collected_by') }}</dt><dd class="font-semibold text-ink">{{ $f->collectedBy->label() }}</dd></div>
    @if ($f->refunded->isPositive())
        <div class="flex justify-between gap-4"><dt class="text-ink-secondary">{{ __('commerce::settlement.refunded') }}</dt><dd class="font-semibold text-danger" dir="ltr">− {{ $f->refunded->format() }}</dd></div>
    @endif
    @if ($f->settlementsPaid->isPositive())
        <div class="flex justify-between gap-4"><dt class="text-ink-secondary">{{ __('commerce::settlement.paid') }}</dt><dd class="font-semibold text-ink" dir="ltr">{{ $f->settlementsPaid->format() }}</dd></div>
    @endif
    <div class="flex justify-between gap-4"><dt class="text-ink-secondary">{{ __('commerce::settlement.owed_to_platform') }}</dt><dd class="font-semibold text-ink" dir="ltr">{{ $f->owedToPlatform->format() }}</dd></div>
    <div class="flex justify-between gap-4"><dt class="text-ink-secondary">{{ __('commerce::settlement.owed_to_vendor') }}</dt><dd class="font-semibold text-ink" dir="ltr">{{ $f->owedToVendor->format() }}</dd></div>
    @if ($f->void)
        <p class="rounded-control bg-subtle px-3 py-2 text-xs text-ink-secondary">{{ __('commerce::settlement.void') }}</p>
    @endif
</dl>
