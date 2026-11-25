{{--
    Stop period create / edit (ADM-033c), loaded into #stop-period-modal: name, company and
    country (empty = all), one-off date range or weekly weekday, active.
--}}
@php
    $weekly = $period->recurrence === \App\Modules\Shipping\Enums\StopPeriodRecurrence::Weekly;
@endphp
<form id="stop-period-form" method="POST" action="{{ $period->exists ? route('admin.shipping.stop-periods.update', $period) : route('admin.shipping.stop-periods.store') }}" data-ajax novalidate class="grid gap-5" data-stop-form
    data-modal-title="{{ $title }}" data-submit-label="{{ __('shipping::common.save') }}">
    @csrf
    @if ($period->exists)
        @method('PUT')
    @endif
    <x-ui::input name="name" :label="__('shipping::stop_periods.fields.name')" :value="$period->name" required maxlength="120" :placeholder="__('shipping::stop_periods.fields.name_placeholder')" />
    <div class="grid gap-5 sm:grid-cols-2">
        <x-ui::select name="shipping_company_id" :label="__('shipping::stop_periods.fields.company')" :options="$companies" :selected="$period->shipping_company_id" :placeholder="__('shipping::stop_periods.all_companies')" allow-clear />
        <x-ui::select name="country_id" :label="__('shipping::stop_periods.fields.country')" :options="$countries" :selected="$period->country_id" :placeholder="__('shipping::stop_periods.all_countries')" allow-clear />
    </div>
    <x-ui::radio-group name="recurrence" :label="__('shipping::stop_periods.fields.recurrence')" :options="$recurrences" :value="$period->recurrence?->value" required data-stop-recurrence />
    <div class="grid gap-5 sm:grid-cols-2">
        <x-ui::date name="starts_on" :label="__('shipping::stop_periods.fields.starts_on')" :value="$period->starts_on?->toDateString()" required />
        <x-ui::date name="ends_on" :label="__('shipping::stop_periods.fields.ends_on')" :value="$period->ends_on?->toDateString()" :helper="__('shipping::stop_periods.fields.ends_on_help')" />
    </div>
    <div data-stop-weekday @if (! $weekly) hidden @endif>
        <x-ui::select name="weekday" :label="__('shipping::stop_periods.fields.weekday')" :options="$weekdays" :selected="$period->weekday?->value" :placeholder="__('shipping::stop_periods.fields.weekday')" :search="false" />
    </div>
    <x-ui::switch name="is_active" :label="__('shipping::stop_periods.fields.active')" :checked="(bool) $period->is_active" />
</form>
