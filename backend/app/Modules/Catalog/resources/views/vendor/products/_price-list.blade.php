{{--
    Price list modal body (VEN-010a, Vendor Panel (new screens) 83:104184): markup mode +
    markup, optional country / variant scope, live preview (server-priced by
    Commerce\Pricing) and a queued apply with progress.
    Data: $product (StoreProduct), $view (StoreProductView), $rows (list<StorePriceRow>).
--}}
@php
    $canManage = (bool) auth('vendor')->user()?->can('catalog.products.manage');
    $countries = collect($rows)->unique('countryId')->mapWithKeys(fn ($row) => [$row->countryId => $row->countryName])->all();
    $variants = collect($view->variants)->mapWithKeys(fn ($variant) => [$variant->id() => $variant->label])->all();
@endphp
<form id="price-list-form" class="grid gap-4" novalidate
    data-modal-title="{{ __('catalog::vendor.price_list.title_named', ['name' => $view->name]) }}"
    data-price-list-form
    data-preview-url="{{ vendor_route('catalog.products.price-list.preview', ['product' => $product->id]) }}"
    data-apply-url="{{ vendor_route('catalog.products.price-list.store') }}">
    <input type="hidden" name="product_ids[]" value="{{ $product->id }}">
    @if ($rows === [])
        <x-ui::empty-state compact icon="globe" :title="__('catalog::vendor.price_list.empty_title')" :description="__('catalog::vendor.products.no_countries')">
            @if (auth('vendor')->user()?->can('settings.countries.view'))
                <x-ui::button variant="secondary" :href="vendor_route('settings.countries.edit')">{{ __('catalog::vendor.price_list.countries_link') }}</x-ui::button>
            @endif
        </x-ui::empty-state>
    @else
        <div class="grid gap-4 sm:grid-cols-2">
            <x-ui::select name="profit_type" :label="__('catalog::vendor.price_list.mode')" :options="\App\Modules\Commerce\Enums\ProfitType::options()" :selected="$rows[0]->rule->type->value" :search="false" :disabled="! $canManage" data-preview-field />
            <x-ui::input name="profit_value" type="number" step="0.01" min="0" :label="__('catalog::vendor.price_list.markup')" :value="$rows[0]->rule->value" :disabled="! $canManage" dir="ltr" inputmode="decimal" data-preview-field />
            <x-ui::select name="country_id" :label="__('catalog::vendor.price_list.country')" :options="$countries" :placeholder="__('catalog::vendor.price_list.all_countries')" allow-clear :disabled="! $canManage" data-preview-field />
            @if ($variants !== [])
                <x-ui::select name="variant_id" :label="__('catalog::vendor.price_list.variant')" :options="$variants" :placeholder="__('catalog::vendor.price_list.all_variants')" allow-clear :disabled="! $canManage" data-preview-field />
            @endif
        </div>
        <div class="max-h-[50vh] overflow-auto rounded-card border border-line">
            <table class="w-full text-sm">
                <thead class="sticky top-0 bg-subtle text-[13px] text-ink-secondary">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-start font-medium">{{ __('catalog::vendor.price_list.country') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-medium">{{ __('catalog::vendor.price_list.variant') }}</th>
                        <th scope="col" class="px-4 py-3 text-end font-medium">{{ __('catalog::vendor.price_list.cost') }}</th>
                        <th scope="col" class="px-4 py-3 text-end font-medium">{{ __('catalog::vendor.price_list.shopper') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line" data-preview-rows aria-live="polite">
                    @foreach ($rows as $row)
                        <tr>
                            <td class="px-4 py-4 text-ink">{{ $row->countryName }}</td>
                            <td class="px-4 py-4 text-ink">{{ $row->variantLabel ?? __('catalog::vendor.price_list.all') }}</td>
                            <td class="px-4 py-4 text-end font-semibold text-ink" dir="ltr">{{ $row->cost->format() }}</td>
                            <td class="px-4 py-4 text-end font-semibold text-ink" dir="ltr">{{ $row->price->format() }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="text-xs text-ink-muted">{{ __('catalog::vendor.price_list.hint') }}</p>
        @include('catalog::vendor.products._run-progress')
    @endif
</form>
