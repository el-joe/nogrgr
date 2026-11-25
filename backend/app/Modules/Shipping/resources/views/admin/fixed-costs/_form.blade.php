{{--
    Fixed cost create / edit (ADM-031a, Figma Admin Panel 71:48842), loaded into
    #fixed-cost-modal: country (one rate per country) and rate per gram (USD), with the
    500 g example.
--}}
<form id="fixed-cost-form" method="POST" action="{{ $cost->exists ? route('admin.shipping.fixed-costs.update', $cost) : route('admin.shipping.fixed-costs.store') }}" data-ajax novalidate class="grid gap-5"
    data-modal-title="{{ $title }}" data-submit-label="{{ __('shipping::common.save') }}">
    @csrf
    @if ($cost->exists)
        @method('PUT')
    @endif
    <x-ui::select name="country_id" :label="__('shipping::fixed_costs.fields.country')" :options="$countries" :selected="$cost->country_id" :placeholder="__('shipping::fixed_costs.fields.country')" :helper="__('shipping::fixed_costs.fields.country_help')" required />
    <x-ui::input name="price_per_gram" type="number" min="0" step="0.000001" :label="__('shipping::fixed_costs.fields.rate')" :value="$cost->exists ? \App\Modules\Shipping\DataTables\FixedShippingCostsDataTable::rate((string) $cost->price_per_gram) : null" placeholder="0.0040" dir="ltr" required />
    <p class="flex items-start gap-2 rounded-control bg-info-bg px-3 py-2.5 text-[13px] text-info">
        <x-ui::icon name="info" :size="16" class="mt-0.5 shrink-0" />
        <span>{{ $example !== null ? __('shipping::fixed_costs.example', ['weight' => number_format($exampleWeight), 'amount' => $example->format()]) : __('shipping::fixed_costs.example_hint', ['weight' => number_format($exampleWeight)]) }}</span>
    </p>
</form>
