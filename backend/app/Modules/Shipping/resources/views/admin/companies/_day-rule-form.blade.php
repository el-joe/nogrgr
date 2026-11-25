{{--
    Day rule create / edit (ADM-033b), loaded into #day-rule-modal: country (one row per
    company), min / max days, default company for that country.
--}}
<form id="day-rule-form" method="POST" action="{{ $rule->exists ? route('admin.shipping.companies.day-rules.update', [$company, $rule->id]) : route('admin.shipping.companies.day-rules.store', $company) }}" data-ajax novalidate class="grid gap-5"
    data-modal-title="{{ $title }}" data-submit-label="{{ __('shipping::common.save') }}">
    @csrf
    @if ($rule->exists)
        @method('PUT')
    @endif
    <x-ui::select name="country_id" :label="__('shipping::companies.day_rules.country')" :options="$countries" :selected="$rule->country_id" :placeholder="__('shipping::companies.day_rules.country')" required />
    <div class="grid gap-5 sm:grid-cols-2">
        <x-ui::input name="min_days" type="number" min="0" max="60" step="1" :label="__('shipping::companies.day_rules.min_days')" :value="$rule->min_days" required dir="ltr" />
        <x-ui::input name="max_days" type="number" min="1" max="90" step="1" :label="__('shipping::companies.day_rules.max_days')" :value="$rule->max_days" required dir="ltr" />
    </div>
    <x-ui::switch name="is_default" :label="__('shipping::companies.day_rules.is_default')" :description="__('shipping::companies.day_rules.is_default_help')" :checked="$isDefault" />
</form>
