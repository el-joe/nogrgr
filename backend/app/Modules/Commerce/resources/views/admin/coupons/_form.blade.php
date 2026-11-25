{{--
    Coupon create / edit / view (ADM-025a, Figma Admin Panel 70:41386), loaded into
    #coupon-modal (or #coupon-view-modal read-only): code, type, value, max discount,
    min spend, usage limit, uses per customer, dates, countries (none = all), stores
    (all / selected), first order only, active. Max discount shows for percentage
    coupons only; the value is hidden for free shipping.
--}}
@php
    $type = $coupon->type?->value ?? 'percentage';
    $scope = $coupon->store_scope?->value ?? 'all';
    $value = match ($type) {
        'percentage' => $coupon->percentage !== null ? rtrim(rtrim(number_format((float) $coupon->percentage, 2, '.', ''), '0'), '.') : null,
        'fixed' => $coupon->amount_minor?->toDecimal(),
        default => null,
    };
    $stores = $coupon->relationLoaded('tenants') ? $coupon->tenants->mapWithKeys(fn ($tenant) => [$tenant->id => $tenant->name])->all() : [];
    $state = ['type' => $type, 'scope' => $scope];
@endphp
<form
    id="{{ $editable ? 'coupon-form' : 'coupon-view' }}"
    method="POST"
    action="{{ $coupon->exists ? route('admin.promotions.coupons.update', $coupon) : route('admin.promotions.coupons.store') }}"
    @if ($editable) data-ajax @endif
    novalidate
    class="grid gap-5"
    data-modal-title="{{ $title }}"
    data-submit-label="{{ __('commerce::promotions.save') }}"
    x-data="{{ json_encode($state) }}"
    x-on:change="if ($event.target.name === 'type') type = $event.target.value; if ($event.target.name === 'store_scope') scope = $event.target.value"
>
    @csrf
    @if ($coupon->exists)
        @method('PUT')
    @endif
    <fieldset @disabled(! $editable) class="grid gap-5">
        @if ($coupon->exists && ! $editable)
            <p class="flex items-start gap-2 rounded-control bg-info-bg px-3 py-2.5 text-[13px] text-info">
                <x-ui::icon name="info" :size="16" class="mt-0.5 shrink-0" />
                <span>{{ __('commerce::coupons.used', ['count' => number_format($usage)]) }}</span>
            </p>
        @endif
        <div class="grid gap-5 sm:grid-cols-2">
            <x-ui::input name="code" :label="__('commerce::coupons.fields.code')" :value="$coupon->code" required maxlength="40" dir="ltr" placeholder="NOGRGR10" data-autofocus />
            <x-ui::select name="type" :label="__('commerce::coupons.fields.type')" :options="$types" :selected="$type" :search="false" required />
            <div x-show="type !== 'free_shipping'" @if ($type === 'free_shipping') x-cloak @endif>
                <x-ui::input name="value" :label="__('commerce::coupons.fields.value')" :value="$value" inputmode="decimal" dir="ltr" placeholder="10" :helper="__('commerce::coupons.fields.value_help')" required />
            </div>
            <div x-show="type === 'percentage'" @if ($type !== 'percentage') x-cloak @endif>
                <x-ui::input name="max_discount" :label="__('commerce::coupons.fields.max_discount')" :value="$coupon->max_discount_minor?->toDecimal()" inputmode="decimal" dir="ltr" placeholder="$20.00" :helper="__('commerce::coupons.fields.max_discount_help')" />
            </div>
            <x-ui::input name="min_spend" :label="__('commerce::coupons.fields.min_spend')" :value="$coupon->exists ? $coupon->minSpend()->toDecimal() : null" inputmode="decimal" dir="ltr" placeholder="$40.00" :helper="__('commerce::coupons.fields.min_spend_help')" />
            <x-ui::input name="usage_limit" type="number" min="1" step="1" :label="__('commerce::coupons.fields.usage_limit')" :value="$coupon->usage_limit" dir="ltr" placeholder="5000" :helper="__('commerce::coupons.fields.usage_limit_help')" />
            <x-ui::input name="per_customer_limit" type="number" min="1" step="1" :label="__('commerce::coupons.fields.per_customer_limit')" :value="$coupon->per_customer_limit" dir="ltr" placeholder="1" :helper="__('commerce::coupons.fields.per_customer_limit_help')" />
            <x-ui::input name="name" :label="__('commerce::coupons.fields.name')" :value="$coupon->name" maxlength="120" />
        </div>
        <x-ui::daterange name="dates" :label="__('commerce::coupons.fields.dates')" :from="$coupon->starts_at?->toDateString()" :to="$coupon->ends_at?->toDateString()" required />
        <x-ui::select name="country_ids" multiple :label="__('commerce::coupons.fields.countries')" :options="$countries" :selected="$coupon->exists ? $coupon->countries->pluck('id')->all() : []" :placeholder="__('commerce::promotions.all_countries')" :helper="__('commerce::promotions.countries_help')" />
        <div class="grid gap-5 sm:grid-cols-2">
            <x-ui::select name="store_scope" :label="__('commerce::coupons.fields.stores')" :options="$scopes" :selected="$scope" :search="false" :helper="__('commerce::promotions.stores_help')" required />
            <div x-show="scope === 'selected'" @if ($scope !== 'selected') x-cloak @endif>
                <x-ui::select name="tenant_ids" multiple :label="__('commerce::coupons.fields.selected_stores')" :options="$stores" :selected="array_keys($stores)" :ajax-url="$editable ? route('admin.promotions.coupons.stores') : null" :placeholder="__('commerce::coupons.fields.search_stores')" />
            </div>
        </div>
        <div class="flex flex-wrap gap-6">
            <x-ui::switch name="first_order_only" :label="__('commerce::coupons.fields.first_order_only')" :checked="(bool) $coupon->first_order_only" />
            <x-ui::switch name="is_active" :label="__('commerce::coupons.fields.active')" :checked="(bool) $coupon->is_active" />
        </div>
    </fieldset>
</form>
