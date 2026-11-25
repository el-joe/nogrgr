{{--
    Admin › Logistics › Branches › Create / Edit (ADM-030a, Figma Admin Panel 71:41812):
    Details tab (translated name, code, country, city, address, contact phone, pickup
    hours) with the Fulfilment card (active, default branch for countries, customer
    pickup, served zones), and the Stock tab (server-side table + CSV import).
--}}
@php
    $isNew = ! $branch->exists;
    $title = $isNew ? __('inventory::branches.create_title') : __('inventory::branches.edit_title');
    $canManage = (bool) auth('admin')->user()?->can('shipping.branches.manage');
    $citiesUrl = route('admin.shipping.branches.cities');
    $countryId = $branch->country_id;
    $phoneCountry = $branch->country?->iso2;
    $tab = request()->query('tab') === 'stock' && ! $isNew ? 'stock' : 'details';
@endphp
<x-layouts.panel surface="admin" :title="$title" data-page="admin-branches">
    <div class="grid gap-6">
        <x-ui::page-header
            :title="$title"
            :breadcrumbs="[__('navigation.sections.logistics') => null, __('inventory::branches.title') => route('admin.shipping.branches.index'), ($isNew ? __('inventory::branches.new') : $branch->name) => null]"
        >
            @unless ($isNew)
                <x-slot:status><x-ui::status :status="$branch->is_active ? 'active' : 'inactive'" /></x-slot:status>
            @endunless
            <x-slot:actions>
                <x-ui::button variant="secondary" :href="route('admin.shipping.branches.index')">{{ __('inventory::branches.cancel') }}</x-ui::button>
                <x-ui::button type="submit" form="branch-form" icon="check">{{ __('inventory::branches.save') }}</x-ui::button>
            </x-slot:actions>
        </x-ui::page-header>

        <x-ui::tabs id="branch-tabs" :active="$tab" :label="$title" :items="array_filter([
            'details' => __('inventory::branches.tabs.details'),
            'stock' => $isNew ? null : ['label' => __('inventory::branches.tabs.stock'), 'count' => number_format($totals['skus'])],
        ])">
            <x-ui::tab-panel tabs="branch-tabs" key="details" :active="$tab === 'details'" class="pt-6">
                <form id="branch-form" method="POST" action="{{ $isNew ? route('admin.shipping.branches.store') : route('admin.shipping.branches.update', $branch) }}" data-ajax data-unsaved-form data-branch-form novalidate
                    class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_340px]">
                    @csrf
                    @unless ($isNew)
                        @method('PUT')
                    @endunless
                    <x-ui::card :title="__('inventory::branches.sections.details')">
                        <div class="grid gap-5">
                            <x-ui::locale-tabs id="branch-i18n" :locales="$locales">
                                @foreach ($locales as $code => $language)
                                    <x-ui::locale-pane parent="branch-i18n" :locale="$code">
                                        <x-ui::input :name="'name['.$code.']'" :label="__('inventory::branches.fields.name')" :value="$isNew ? null : $branch->getTranslation('name', $code, false)" :required="in_array($code, \App\Modules\Catalog\Support\CatalogLocales::REQUIRED, true)" maxlength="120" :dir="$language['dir']" />
                                    </x-ui::locale-pane>
                                @endforeach
                            </x-ui::locale-tabs>
                            <div class="grid gap-5 sm:grid-cols-2">
                                <x-ui::input name="code" :label="__('inventory::branches.fields.code')" :value="$branch->code" required maxlength="32" dir="ltr" placeholder="RUH-01" />
                                <x-ui::select name="country_id" :label="__('inventory::branches.fields.country')" :options="$countries" :selected="$countryId" :placeholder="__('inventory::branches.fields.country')" required data-branch-country />
                                <x-ui::select name="city_id" :label="__('inventory::branches.fields.city')" :options="$branch->city ? [$branch->city->id => $branch->city->name] : []" :selected="$branch->city_id" :placeholder="__('inventory::branches.fields.city')" allow-clear :ajax-url="$countryId ? $citiesUrl.'?country='.$countryId : null" :data-cities-url="$citiesUrl" data-branch-city />
                                <x-ui::input name="pickup_hours" :label="__('inventory::branches.fields.pickup_hours')" :value="$branch->pickup_hours" maxlength="120" placeholder="Sun–Thu 08:00–20:00" />
                            </div>
                            <x-ui::input name="address" :label="__('inventory::branches.fields.address')" :value="$branch->address" maxlength="255" />
                            <x-ui::phone name="phone" :label="__('inventory::branches.fields.phone')" :value="$branch->phone" :country="$phoneCountry" required class="sm:max-w-sm" />
                        </div>
                    </x-ui::card>

                    <x-ui::card :title="__('inventory::branches.sections.fulfilment')">
                        <div class="grid gap-4">
                            <x-ui::switch name="is_active" :label="__('inventory::branches.fields.active')" :checked="(bool) $branch->is_active" />
                            <x-ui::select name="default_country_ids" multiple :label="__('inventory::branches.fields.default_for')" :options="$countries" :selected="$branch->exists ? $branch->defaultCountries->pluck('id')->all() : []" :placeholder="__('inventory::branches.fields.default_none')" :helper="__('inventory::branches.fields.default_help')" />
                            <x-ui::switch name="allows_pickup" :label="__('inventory::branches.fields.allows_pickup')" :checked="(bool) $branch->allows_pickup" />
                            <x-ui::select name="zone_city_ids" multiple :label="__('inventory::branches.fields.zones')" :options="$branch->exists ? $branch->zones->mapWithKeys(fn ($city) => [$city->id => $city->name])->all() : []" :selected="$branch->exists ? $branch->zones->pluck('id')->all() : []" :placeholder="__('inventory::branches.fields.zones_placeholder')" :ajax-url="$countryId ? $citiesUrl.'?country='.$countryId : null" :data-cities-url="$citiesUrl" :helper="__('inventory::branches.fields.zones_help')" data-branch-zones />
                        </div>
                    </x-ui::card>

                    <div data-unsaved-bar hidden class="sticky bottom-0 z-20 -mx-4 flex flex-wrap items-center justify-between gap-3 border-t border-line bg-surface px-4 py-3 shadow-overlay sm:-mx-6 sm:px-6 lg:col-span-2 xl:-mx-8 xl:px-8">
                        <p class="text-sm font-medium text-ink">{{ __('inventory::branches.unsaved') }}</p>
                        <div class="flex gap-3">
                            <x-ui::button variant="secondary" data-unsaved-discard>{{ __('inventory::branches.discard') }}</x-ui::button>
                            <x-ui::button type="submit" icon="check">{{ __('inventory::branches.save') }}</x-ui::button>
                        </div>
                    </div>
                </form>
            </x-ui::tab-panel>

            @unless ($isNew)
                <x-ui::tab-panel tabs="branch-tabs" key="stock" :active="$tab === 'stock'" class="grid gap-4 pt-6">
                    <p class="text-sm text-ink-secondary">{{ __('inventory::branches.stock.summary', ['skus' => number_format($totals['skus']), 'units' => number_format($totals['units'])]) }}</p>
                    <x-ui::datatable
                        id="branch-stock"
                        :url="route('admin.shipping.branches.stock', $branch)"
                        :columns="[
                            ['data' => 'variant', 'title' => __('inventory::branches.stock.variant'), 'searchable' => false],
                            ['data' => 'on_hand', 'title' => __('inventory::branches.stock.on_hand'), 'align' => 'end', 'searchable' => false],
                            ['data' => 'reserved', 'title' => __('inventory::branches.stock.reserved'), 'align' => 'end', 'searchable' => false],
                            ['data' => 'available', 'title' => __('inventory::branches.stock.available'), 'align' => 'end', 'searchable' => false],
                        ]"
                        :order="[[1, 'desc']]"
                        :empty-title="__('inventory::branches.stock.empty_title')"
                        :empty-description="__('inventory::branches.stock.empty_description')"
                    >
                        <x-slot:filters>
                            <x-ui::filters-bar target="branch-stock" :search-placeholder="__('inventory::branches.stock.search')">
                                @if ($canManage)
                                    <x-ui::button variant="secondary" size="sm" icon="upload" data-overlay-open="branch-import-modal">{{ __('inventory::branches.stock.import') }}</x-ui::button>
                                @endif
                            </x-ui::filters-bar>
                        </x-slot:filters>
                    </x-ui::datatable>
                </x-ui::tab-panel>
            @endunless
        </x-ui::tabs>
    </div>

    @if (! $isNew && $canManage)
        <x-ui::modal id="branch-import-modal" :title="__('inventory::branches.stock.import_title')" :subtitle="__('inventory::branches.stock.import_help')" size="sm" close-on-success>
            <form id="branch-import-form" method="POST" action="{{ route('admin.shipping.branches.stock.import', $branch) }}" enctype="multipart/form-data" data-ajax novalidate class="grid gap-4">
                @csrf
                <x-ui::file-upload name="file" :label="__('inventory::branches.stock.file')" accept=".csv,text/csv" required />
            </form>
            <x-slot:footer>
                <x-ui::button variant="secondary" data-overlay-close>{{ __('inventory::branches.cancel') }}</x-ui::button>
                <x-ui::button type="submit" form="branch-import-form" icon="upload">{{ __('inventory::branches.stock.import') }}</x-ui::button>
            </x-slot:footer>
        </x-ui::modal>
    @endif
</x-layouts.panel>
