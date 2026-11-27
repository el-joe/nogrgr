{{--
    Order totals (snapshot columns): subtotal, flash discount, shipping, coupon, tax, total.
    display_subtotal = Σ(sub_total + fixed_shipping), so the label says so when an order line
    carries fixed shipping (RQA-VEN-006); the item count is pluralised (RQA-VEN-014).
--}}
@php
    $o = $order;
    $itemCount = (int) $o->items->sum('quantity');
    $subtotalKey = $o->items->sum('fixed_shipping') > 0 ? 'subtotal_with_fixed' : 'subtotal';
@endphp
<dl class="grid gap-2 text-sm">
    <div class="flex justify-between gap-4"><dt class="text-ink-secondary">{{ trans_choice('commerce::orders_admin.totals.'.$subtotalKey, $itemCount, ['count' => $itemCount]) }}</dt><dd class="text-ink" dir="ltr">{{ $o->money('display_subtotal')->format() }}</dd></div>
    @if ($o->flash_discount > 0)
        <div class="flex justify-between gap-4"><dt class="text-ink-secondary">{{ __('commerce::orders_admin.totals.flash') }}</dt><dd class="text-success" dir="ltr">− {{ $o->money('flash_discount')->format() }}</dd></div>
    @endif
    <div class="flex justify-between gap-4"><dt class="text-ink-secondary">{{ __('commerce::orders_admin.totals.shipping', ['company' => $o->shipping_company_name ?? '—', 'weight' => number_format($o->items->sum('weight_grams'))]) }}</dt><dd class="text-ink" dir="ltr">{{ $o->money('shipping_charge')->format() }}</dd></div>
    @if ($o->coupon_discount > 0)
        <div class="flex justify-between gap-4"><dt class="text-ink-secondary">{{ __('commerce::orders_admin.totals.coupon_'.($o->coupon_source?->value ?? 'vendor'), ['code' => $o->coupon_code]) }}</dt><dd class="text-success" dir="ltr">− {{ $o->money('coupon_discount')->format() }}</dd></div>
    @endif
    @if ($o->tax > 0)
        <div class="flex justify-between gap-4"><dt class="text-ink-secondary">{{ __('commerce::orders_admin.totals.tax') }}</dt><dd class="text-ink" dir="ltr">{{ $o->money('tax')->format() }}</dd></div>
    @endif
    <div class="mt-1 flex justify-between gap-4 border-t border-line pt-3 font-semibold"><dt class="text-ink">{{ __('commerce::orders_admin.totals.total') }}</dt><dd class="text-ink" dir="ltr">{{ $o->money('grand_total')->format() }}</dd></div>
</dl>
