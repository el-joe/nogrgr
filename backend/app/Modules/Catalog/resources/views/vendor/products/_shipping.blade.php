{{--
    Shipping details modal body (VEN-010e, Tenant Panel Layout 12:16200): destination select,
    then the fee (fixed shipping + zone fee from Shipping\ProductShippingDetailsService)
    and the delivery window for the chosen country. Data: $product, $rows.
--}}
<div class="grid gap-5" data-shipping-details>
    @if ($rows === [])
        <x-ui::empty-state compact icon="truck" :description="__('catalog::vendor.products.no_countries')" />
    @else
        <p class="text-lg font-semibold text-ink-brand">{{ __('catalog::vendor.shipping.select_hint') }}</p>
        <x-ui::select name="shipping_country" :label="__('catalog::vendor.shipping.destination')" :options="collect($rows)->mapWithKeys(fn ($row) => [$row['country_id'] => $row['country'].' ('.$row['iso2'].')'])->all()" :selected="$rows[0]['country_id']" data-shipping-select class="sm:max-w-[300px]" />
        @foreach ($rows as $index => $row)
            <dl class="grid gap-3" data-shipping-country="{{ $row['country_id'] }}" @if ($index > 0) hidden @endif>
                @if ($row['available'])
                    <div>
                        <dt class="text-sm text-ink-secondary">{{ __('catalog::vendor.shipping.fee') }}</dt>
                        <dd class="text-2xl font-bold text-ink-brand" dir="ltr">{{ $row['zone_fee']->format() }}</dd>
                    </div>
                    <div class="grid gap-1 text-sm">
                        <div class="flex justify-between gap-4"><dt class="text-ink-secondary">{{ __('catalog::vendor.shipping.fixed') }}</dt><dd class="font-medium text-ink" dir="ltr">{{ $row['fixed_shipping']->format() }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-ink-secondary">{{ __('catalog::vendor.shipping.weight') }}</dt><dd class="font-medium text-ink" dir="ltr">{{ number_format($row['weight_grams']) }} g</dd></div>
                        @if ($row['zone'])
                            <div class="flex justify-between gap-4"><dt class="text-ink-secondary">{{ __('catalog::vendor.shipping.zone') }}</dt><dd class="font-medium text-ink">{{ $row['zone'] }}{{ $row['band'] ? ' · '.$row['band'] : '' }}</dd></div>
                        @endif
                        <div class="flex justify-between gap-4"><dt class="text-ink-secondary">{{ __('catalog::vendor.shipping.delivery') }}</dt><dd class="font-medium text-ink" dir="ltr">{{ \Illuminate\Support\Carbon::parse($row['min_date'])->translatedFormat('j M') }} – {{ \Illuminate\Support\Carbon::parse($row['max_date'])->translatedFormat('j M') }}</dd></div>
                    </div>
                @else
                    <div class="rounded-control bg-warning-bg p-3 text-sm text-warning">{{ $row['message'] ?? __('catalog::vendor.shipping.unavailable') }}</div>
                @endif
            </dl>
        @endforeach
    @endif
</div>
