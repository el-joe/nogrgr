{{--
    Store coupon create / edit / view (VEN-040a, Figma Tenant Panel 87:111915), loaded
    into #vendor-coupon-modal (or #vendor-coupon-view-modal read-only): code, type, value,
    min spend, dates, countries (the store's selling countries; none = all), products (a
    store coupon covers the whole cart), "deducted from your share" note; plus max
    discount (percentage), usage limits, first order only and active.
--}}
@php
    $type = $coupon->type?->value ?? 'percentage';
    $value = match ($type) {
        'percentage' => $coupon->percentage !== null ? rtrim(rtrim(number_format((float) $coupon->percentage, 2, '.', ''), '0'), '.') : null,
        'fixed' => $coupon->amount_minor?->toDecimal(),
        default => null,
    };
@endphp
<form
    id="{{ $editable ? 'vendor-coupon-form' : 'vendor-coupon-view' }}"
    method="POST"
    action="{{ $coupon->exists ? vendor_route('marketing.coupons.update', ['coupon' => $coupon->id]) : vendor_route('marketing.coupons.store') }}"
    @if ($editable) data-ajax @endif
    novalidate
    class="grid gap-5"
    data-modal-title="{{ $title }}"
    data-submit-label="{{ __('commerce::promotions.save') }}"
    x-data="{{ json_encode(['type' => $type]) }}"
    x-on:change="if ($event.target.name === 'type') type = $event.target.value"
>
    @csrf
    @if ($coupon->exists)
        @method('PUT')
    @endif
    <fieldset @disabled(! $editable) class="grid gap-5">
        @if ($coupon->exists && ! $editable)
            <p class="flex items-start gap-2 rounded-control bg-info-bg px-3 py-2.5 text-[13px] text-info">
                <x-ui::icon name="info" :size="16" class="mt-0.5 shrink-0" />
                <span>{{ __('commerce::vendor_promotions.coupons.used', ['count' => number_format($usage)]) }}</span>
            </p>
        @endif
        <div class="grid gap-5 sm:grid-cols-2">
            <x-ui::input name="code" :label="__('commerce::coupons.fields.code')" :value="$coupon->code" required maxlength="40" dir="ltr" placeholder="MEGZ15" data-autofocus />
            <x-ui::select name="type" :label="__('commerce::coupons.fields.type')" :options="$types" :selected="$type" :search="false" required />
            <div x-show="type !== 'free_shipping'" @if ($type === 'free_shipping') x-cloak @endif>
                <x-ui::input name="value" :label="__('commerce::coupons.fields.value')" :value="$value" inputmode="decimal" dir="ltr" placeholder="15%" :helper="__('commerce::coupons.fields.value_help')" required />
            </div>
            <x-ui::input name="min_spend" :label="__('commerce::coupons.fields.min_spend')" :value="$coupon->exists ? $coupon->minSpend()->toDecimal() : null" inputmode="decimal" dir="ltr" placeholder="$30.00" :helper="__('commerce::coupons.fields.min_spend_help')" />
        </div>
        <x-ui::daterange name="dates" :label="__('commerce::coupons.fields.dates')" :from="$coupon->starts_at?->toDateString()" :to="$coupon->ends_at?->toDateString()" required />
        <x-ui::select name="country_ids" multiple :label="__('commerce::coupons.fields.countries')" :options="$countries" :selected="$coupon->exists ? $coupon->countryIds() : []" :placeholder="__('commerce::promotions.all_countries')" :helper="__('commerce::promotions.countries_help')" />
        <x-ui::select name="products_scope" :label="__('commerce::vendor_promotions.coupons.products')" :options="['all' => __('commerce::vendor_promotions.coupons.all_products')]" selected="all" :search="false" disabled :helper="__('commerce::vendor_promotions.coupons.products_help')" />
        <div class="grid gap-5 sm:grid-cols-2">
            <div x-show="type === 'percentage'" @if ($type !== 'percentage') x-cloak @endif>
                <x-ui::input name="max_discount" :label="__('commerce::coupons.fields.max_discount')" :value="$coupon->max_discount_minor?->toDecimal()" inputmode="decimal" dir="ltr" placeholder="$20.00" :helper="__('commerce::coupons.fields.max_discount_help')" />
            </div>
            <x-ui::input name="usage_limit" type="number" min="1" step="1" :label="__('commerce::coupons.fields.usage_limit')" :value="$coupon->usage_limit" dir="ltr" placeholder="1000" :helper="__('commerce::vendor_promotions.coupons.usage_limit_help')" />
            <x-ui::input name="per_customer_limit" type="number" min="1" step="1" :label="__('commerce::coupons.fields.per_customer_limit')" :value="$coupon->per_customer_limit" dir="ltr" placeholder="1" :helper="__('commerce::coupons.fields.per_customer_limit_help')" />
            <x-ui::input name="name" :label="__('commerce::coupons.fields.name')" :value="$coupon->name" maxlength="120" />
        </div>
        <div class="flex flex-wrap gap-6">
            <x-ui::switch name="first_order_only" :label="__('commerce::coupons.fields.first_order_only')" :checked="(bool) $coupon->first_order_only" />
            <x-ui::switch name="is_active" :label="__('commerce::coupons.fields.active')" :checked="(bool) $coupon->is_active" />
        </div>
        <p class="flex items-start gap-2 rounded-control bg-info-bg px-3 py-2.5 text-[13px] text-info">
            <x-ui::icon name="info" :size="16" class="mt-0.5 shrink-0" />
            <span>{{ __('commerce::vendor_promotions.coupons.deducted') }}</span>
        </p>
    </fieldset>
</form>
