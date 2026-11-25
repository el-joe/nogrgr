{{--
    Admin › Logistics › Shipping › Fixed costs (ADM-031, Figma Admin Panel 71:42662):
    server-side table (country, rate per gram, 500 g example, updated), country search,
    edit / delete, the "Add country" modal (ADM-031a) and the display-price calculator.
--}}
@php
    $canManage = (bool) auth('admin')->user()?->can('shipping.fixed-costs.manage');
@endphp
<x-layouts.panel surface="admin" :title="__('shipping::fixed_costs.title')" data-page="admin-shipping">
    <div class="grid gap-6">
        <x-ui::page-header
            :title="__('shipping::fixed_costs.title')"
            :subtitle="__('shipping::fixed_costs.subtitle')"
            :breadcrumbs="[__('navigation.sections.logistics') => null, __('navigation.admin.shipping') => null, __('shipping::fixed_costs.breadcrumb') => null]"
        >
            @if ($canManage)
                <x-slot:actions>
                    <x-ui::button icon="plus" data-remote-modal="fixed-cost-modal" :data-url="route('admin.shipping.fixed-costs.create')">{{ __('shipping::fixed_costs.add') }}</x-ui::button>
                </x-slot:actions>
            @endif
        </x-ui::page-header>

        <div class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
            <x-ui::datatable
                id="fixed-costs"
                :url="route('admin.shipping.fixed-costs.data')"
                :columns="[
                    ['data' => 'country', 'title' => __('shipping::fixed_costs.columns.country'), 'searchable' => false],
                    ['data' => 'price_per_gram', 'title' => __('shipping::fixed_costs.columns.rate'), 'align' => 'end', 'searchable' => false],
                    ['data' => 'example', 'title' => __('shipping::fixed_costs.columns.example', ['weight' => '500 g']), 'align' => 'end', 'searchable' => false],
                    ['data' => 'updated_at', 'title' => __('shipping::fixed_costs.columns.updated'), 'searchable' => false],
                    ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
                ]"
                :order="[[0, 'asc']]"
                :empty-title="__('shipping::fixed_costs.empty_title')"
                :empty-description="__('shipping::fixed_costs.empty_description')"
            >
                <x-slot:filters>
                    <x-ui::filters-bar target="fixed-costs" :search-placeholder="__('shipping::fixed_costs.search')" />
                </x-slot:filters>
                @if ($canManage)
                    <x-slot:empty>
                        <x-ui::button icon="plus" data-remote-modal="fixed-cost-modal" :data-url="route('admin.shipping.fixed-costs.create')">{{ __('shipping::fixed_costs.add') }}</x-ui::button>
                    </x-slot:empty>
                @endif
            </x-ui::datatable>

            <x-ui::card :title="__('shipping::fixed_costs.calculator.title')">
                <form class="grid gap-4" data-fixed-calculator data-url="{{ route('admin.shipping.fixed-costs.calculate') }}" novalidate>
                    <x-ui::select name="country_id" :label="__('shipping::fixed_costs.fields.country')" :options="$countries" :placeholder="__('shipping::fixed_costs.fields.country')" data-calc-field />
                    <x-ui::input name="weight_grams" type="number" min="0" step="1" :label="__('shipping::fixed_costs.calculator.weight')" value="1240" dir="ltr" data-calc-field />
                    <x-ui::input name="sale_price" type="number" min="0" step="0.01" :label="__('shipping::fixed_costs.calculator.sale_price')" value="59.00" dir="ltr" data-calc-field />
                    <p data-calc-result class="flex items-start gap-2 rounded-control bg-subtle px-3 py-2.5 text-[13px] text-ink-secondary" role="status" aria-live="polite">
                        <x-ui::icon name="info" :size="16" class="mt-0.5 shrink-0" />
                        <span data-calc-text>{{ __('shipping::fixed_costs.calculator.hint') }}</span>
                    </p>
                    <p data-calc-error hidden class="flex items-start gap-2 rounded-control bg-danger-bg px-3 py-2.5 text-[13px] text-danger" role="alert">
                        <x-ui::icon name="alert-triangle" :size="16" class="mt-0.5 shrink-0" />
                        <span>{{ __('shipping::fixed_costs.calculator.error') }}</span>
                    </p>
                </form>
            </x-ui::card>
        </div>
    </div>

    @if ($canManage)
        <x-ui::modal id="fixed-cost-modal" :title="__('shipping::fixed_costs.create_title')" size="sm">
            @include('shipping::admin.partials.remote-states')
            <x-slot:footer>
                <x-ui::button variant="secondary" data-overlay-close>{{ __('shipping::common.cancel') }}</x-ui::button>
                <x-ui::button type="submit" form="fixed-cost-form" icon="check" data-remote-submit>{{ __('shipping::common.save') }}</x-ui::button>
            </x-slot:footer>
        </x-ui::modal>
    @endif
</x-layouts.panel>
