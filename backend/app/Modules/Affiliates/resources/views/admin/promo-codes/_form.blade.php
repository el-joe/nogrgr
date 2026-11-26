{{--
    ADM-063 "New promo code" / edit (Figma 74:68877): affiliate (Select2 search), code,
    discount type + value, valid until; commission override, minimum spend, usage limit,
    active. $code (nullable), $types, $formAction.
--}}
@php
    $decimal = static fn (?int $value): ?string => $value !== null ? number_format($value / 100, 2, '.', '') : null;
@endphp
<form id="promo-code-form" method="POST" action="{{ $formAction }}" data-ajax class="grid gap-4"
    data-modal-title="{{ $code ? __('affiliates::admin.promo.edit_title', ['code' => $code->code]) : __('affiliates::admin.promo.create') }}">
    @csrf
    @if ($code)
        @method('PUT')
    @endif
    <x-ui::select name="affiliate_id" :label="__('affiliates::admin.promo.fields.affiliate_id')" :options="$code?->affiliate ? [$code->affiliate->id => $code->affiliate->name] : []" :selected="$code?->affiliate_id" :placeholder="__('affiliates::admin.promo.choose_affiliate')" :ajax-url="route('admin.affiliates.promo-codes.affiliates')" required />
    <div class="grid gap-4 sm:grid-cols-2">
        <x-ui::input name="code" :value="$code?->code" :label="__('affiliates::admin.promo.fields.code')" maxlength="32" required dir="ltr" />
        <x-ui::date name="ends_at" :value="$code?->ends_at?->toDateString()" :min="now()->toDateString()" :label="__('affiliates::admin.promo.fields.ends_at')" required />
    </div>
    <div class="grid gap-4 sm:grid-cols-2">
        <x-ui::select name="discount_type" :label="__('affiliates::admin.promo.fields.discount_type')" :options="$types" :selected="$code?->discount_type->value ?? 'percentage'" :search="false" required />
        <x-ui::input name="discount_value" type="number" step="0.01" min="0.01" :value="$decimal($code?->discount_value)" :label="__('affiliates::admin.promo.fields.discount_value')" :helper="__('affiliates::admin.promo.value_help')" required dir="ltr" />
    </div>
    <div class="grid gap-4 sm:grid-cols-2">
        <x-ui::select name="commission_type" :label="__('affiliates::admin.promo.fields.commission_type')" :options="$types" :selected="$code?->commission_type?->value" :placeholder="__('affiliates::admin.promo.commission_default')" :search="false" allow-clear />
        <x-ui::input name="commission_value" type="number" step="0.01" min="0" :value="$decimal($code?->commission_value)" :label="__('affiliates::admin.promo.fields.commission_value')" dir="ltr" />
    </div>
    <div class="grid gap-4 sm:grid-cols-2">
        <x-ui::input name="min_spend" type="number" step="0.01" min="0" :value="$decimal($code?->min_spend)" :label="__('affiliates::admin.promo.fields.min_spend')" dir="ltr" />
        <x-ui::input name="max_uses" type="number" step="1" min="1" :value="$code?->max_uses" :label="__('affiliates::admin.promo.fields.max_uses')" :helper="__('affiliates::admin.promo.max_uses_help')" dir="ltr" />
    </div>
    <x-ui::switch name="is_active" :label="__('affiliates::admin.promo.fields.is_active')" :checked="$code?->is_active ?? true" />
    @include('commerce::partials.modal-actions', ['submit' => __('affiliates::admin.save'), 'cancel' => __('affiliates::admin.cancel')])
</form>
