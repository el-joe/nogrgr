{{--
    Admin › Catalog › Products › Create / Edit (ADM-011a–h, Figma Admin Panel 46:14760 ·
    47:15543 · 65:28688): one form, eight tabs — General & translations, Variants
    (attribute builder + per-variant SKU / price / weight / image), Pricing & weight,
    Media gallery (webp / avif job state), Countries & shipping, Categories / labels /
    badges, Tenant assignment, Stock per branch. Fields are validated on blur; the sticky
    bar and the leave-page warning guard unsaved changes.
--}}
@php
    $isNew = ! $product->exists;
    $title = $isNew ? __('catalog::products.create_title') : __('catalog::products.edit_title');
    $required = \App\Modules\Catalog\Support\CatalogLocales::REQUIRED;
    $value = fn (string $field, string $code) => $isNew ? null : $product->getTranslation($field, $code, false);
    $priceValue = $isNew ? null : $product->costBasis()->toDecimal();
    $compareValue = $isNew ? null : $product->compareAtPrice()?->toDecimal();
    $costValue = $isNew ? null : $product->cost_price?->toDecimal();
    $statusCounts = collect($gallery)->countBy(fn ($item) => $item['status']->value);
    $tab = request()->query('tab');
    $tabs = ['general', 'variants', 'pricing', 'media', 'countries', 'taxonomy', 'tenants', 'stock'];
    $tab = in_array($tab, $tabs, true) ? $tab : 'general';
    $variantCount = count($builder['variants']);
    $fixedShipping = $isNew ? [] : (array) ($product->fixed_shipping_costs ?? []);
@endphp
<x-layouts.panel surface="admin" :title="$title" data-page="admin-products">
    <form
        id="product-form"
        method="POST"
        action="{{ $isNew ? route('admin.catalog.products.store') : route('admin.catalog.products.update', $product) }}"
        enctype="multipart/form-data"
        data-ajax
        data-unsaved-form
        data-product-form
        data-validate-url="{{ route('admin.catalog.products.validate', $isNew ? [] : [$product]) }}"
        class="grid gap-6"
        novalidate
    >
        @csrf
        @unless ($isNew)
            @method('PUT')
        @endunless
        <script type="application/json" data-product-builder>@json($builder)</script>

        <x-ui::page-header
            :title="$title"
            :subtitle="$isNew ? null : __('catalog::products.last_saved', ['time' => $product->updated_at?->diffForHumans()])"
            :breadcrumbs="[__('navigation.sections.catalog') => null, __('catalog::products.title') => route('admin.catalog.products.index'), ($isNew ? __('catalog::products.new') : $product->name) => null]"
        >
            @unless ($isNew)
                <x-slot:status><x-ui::status :status="$product->status === \App\Modules\Catalog\Enums\ProductStatus::Published ? 'active' : 'draft'">{{ $product->status->label() }}</x-ui::status></x-slot:status>
            @endunless
            <x-slot:actions>
                <x-ui::button variant="secondary" :href="route('admin.catalog.products.index')">{{ __('catalog::products.cancel') }}</x-ui::button>
                <x-ui::button type="submit" icon="check">{{ __('catalog::products.save') }}</x-ui::button>
            </x-slot:actions>
        </x-ui::page-header>

        <div class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_320px]">
            <x-ui::tabs id="product-tabs" :active="$tab" :label="$title" :items="[
                'general' => __('catalog::products.tabs.general'),
                'variants' => ['label' => __('catalog::products.tabs.variants'), 'count' => $variantCount],
                'pricing' => __('catalog::products.tabs.pricing'),
                'media' => __('catalog::products.tabs.media'),
                'countries' => __('catalog::products.tabs.countries'),
                'taxonomy' => __('catalog::products.tabs.taxonomy'),
                'tenants' => __('catalog::products.tabs.tenants'),
                'stock' => __('catalog::products.tabs.stock'),
            ]">
                {{-- 011a General & translations --}}
                <x-ui::tab-panel tabs="product-tabs" key="general" :active="$tab === 'general'" class="pt-6">
                    <x-ui::card :title="__('catalog::products.sections.general')" :subtitle="__('catalog::products.sections.translations_hint')">
                        <div class="grid gap-5">
                            <x-ui::locale-tabs id="product-i18n" :locales="$locales">
                                @foreach ($locales as $code => $language)
                                    <x-ui::locale-pane parent="product-i18n" :locale="$code">
                                        <x-ui::input :name="'name['.$code.']'" :label="__('catalog::products.fields.name')" :value="$value('name', $code)" :required="in_array($code, $required, true)" maxlength="191" :dir="$language['dir']" data-validate :data-slug-source="$code === 'en' ? '' : null" />
                                        <x-ui::textarea :name="'summary['.$code.']'" :label="__('catalog::products.fields.summary')" :value="$value('summary', $code)" :rows="2" :maxlength="500" :dir="$language['dir']" />
                                        <x-ui::textarea :name="'description['.$code.']'" :label="__('catalog::products.fields.description')" :value="$value('description', $code)" :rows="6" :maxlength="20000" :dir="$language['dir']" />
                                        <x-ui::input :name="'meta_title['.$code.']'" :label="__('catalog::products.fields.meta_title')" :value="$value('meta_title', $code)" maxlength="160" :dir="$language['dir']" />
                                        <x-ui::textarea :name="'meta_description['.$code.']'" :label="__('catalog::products.fields.meta_description')" :value="$value('meta_description', $code)" :rows="2" :maxlength="320" :dir="$language['dir']" />
                                        <x-ui::input :name="'meta_keywords['.$code.']'" :label="__('catalog::products.fields.meta_keywords')" :value="$value('meta_keywords', $code)" maxlength="255" :dir="$language['dir']" />
                                    </x-ui::locale-pane>
                                @endforeach
                            </x-ui::locale-tabs>
                            <div class="grid gap-5 sm:grid-cols-2">
                                <x-ui::input name="sku" :label="__('catalog::products.fields.sku')" :value="$product->sku" required maxlength="64" dir="ltr" data-validate />
                                <x-ui::input name="slug" :label="__('catalog::products.fields.slug')" :value="$product->slug" :helper="__('catalog::products.fields.slug_help')" maxlength="180" dir="ltr" data-validate data-slug-target :data-slug-locked="$isNew ? null : ''" />
                            </div>
                        </div>
                    </x-ui::card>
                </x-ui::tab-panel>

                {{-- 011c Variants --}}
                <x-ui::tab-panel tabs="product-tabs" key="variants" :active="$tab === 'variants'" class="grid gap-6 pt-6">
                    <x-ui::card :title="__('catalog::products.sections.attributes')" :subtitle="__('catalog::products.sections.attributes_hint')">
                        @if ($builder['attributes'] === [])
                            <x-ui::empty-state compact icon="list" :description="__('catalog::products.variants.no_attributes')">
                                @can('catalog.attributes.manage')
                                    <x-ui::button variant="secondary" size="sm" icon="plus" :href="route('admin.catalog.attributes.create')">{{ __('catalog::attributes.add') }}</x-ui::button>
                                @endcan
                            </x-ui::empty-state>
                        @else
                            <div class="grid gap-3" data-attribute-groups></div>
                            <div class="mt-4 flex flex-wrap items-end gap-3">
                                <x-ui::select name="builder_attribute" :label="__('catalog::products.variants.add_attribute')" label-hidden :options="[]" :placeholder="__('catalog::products.variants.choose_attribute')" class="w-56" data-attribute-picker data-unsaved-ignore />
                                <x-ui::button variant="secondary" size="sm" icon="plus" data-attribute-add>{{ __('catalog::products.variants.add_attribute') }}</x-ui::button>
                            </div>
                        @endif
                    </x-ui::card>
                    <x-ui::card :title="__('catalog::products.sections.variants')" :subtitle="__('catalog::products.sections.variants_hint')">
                        <p data-variants-empty class="py-6 text-center text-sm text-ink-secondary">{{ __('catalog::products.variants.empty') }}</p>
                        <div data-variants-table hidden class="relative overflow-x-auto rounded-control border border-line">
                            <table class="w-full min-w-[720px] text-sm">
                                <thead class="bg-subtle text-xs text-ink-secondary">
                                    <tr>
                                        <th scope="col" class="px-3 py-2.5 text-start font-medium">{{ __('catalog::products.variants.variant') }}</th>
                                        <th scope="col" class="px-3 py-2.5 text-start font-medium">{{ __('catalog::products.variants.sku') }}</th>
                                        <th scope="col" class="px-3 py-2.5 text-start font-medium">{{ __('catalog::products.variants.price') }} (USD)</th>
                                        <th scope="col" class="px-3 py-2.5 text-start font-medium">{{ __('catalog::products.variants.weight') }}</th>
                                        <th scope="col" class="px-3 py-2.5 text-start font-medium">{{ __('catalog::products.variants.image') }}</th>
                                        <th scope="col" class="px-3 py-2.5 text-start font-medium">{{ __('catalog::products.variants.active') }}</th>
                                    </tr>
                                </thead>
                                <tbody data-variant-rows class="divide-y divide-line"></tbody>
                            </table>
                        </div>
                    </x-ui::card>
                    <template data-attribute-group-template>
                        <div data-attribute-group class="flex flex-wrap items-center gap-3 rounded-control border border-line p-3">
                            <span data-attribute-name class="w-28 shrink-0 text-sm font-medium text-ink"></span>
                            <div class="min-w-0 flex-1"><select multiple data-attribute-values class="ui-control" data-unsaved-ignore></select></div>
                            <x-ui::icon-button icon="trash" size="sm" :label="__('catalog::products.variants.remove_attribute', ['name' => ''])" data-attribute-remove />
                        </div>
                    </template>
                    <template data-variant-row-template>
                        <tr data-variant-row>
                            <td class="px-3 py-2"><span class="flex items-center gap-2"><span data-swatch class="hidden size-3 shrink-0 rounded-full border border-line"></span><span data-variant-label class="font-medium text-ink"></span></span></td>
                            <td class="px-3 py-2"><input type="text" data-field="sku" data-label="{{ __('catalog::products.variants.sku') }}" maxlength="64" dir="ltr" class="ui-control h-9 w-36 px-2" data-validate></td>
                            <td class="px-3 py-2"><input type="number" data-field="price" data-label="{{ __('catalog::products.variants.price') }}" min="0" step="0.01" inputmode="decimal" dir="ltr" class="ui-control h-9 w-24 px-2" data-validate></td>
                            <td class="px-3 py-2"><input type="number" data-field="weight_grams" data-label="{{ __('catalog::products.variants.weight') }}" min="0" step="1" dir="ltr" class="ui-control h-9 w-20 px-2" data-validate></td>
                            <td class="px-3 py-2">
                                <span class="flex items-center gap-2">
                                    <img data-variant-image alt="" class="hidden size-8 rounded-chip object-cover">
                                    <input type="file" data-field="image" data-label="{{ __('catalog::products.variants.image') }}" accept="image/jpeg,image/png,image/webp" class="w-40 text-xs file:me-2 file:rounded-chip file:border-0 file:bg-subtle file:px-2 file:py-1 file:text-xs">
                                    <label data-remove-image-wrap class="hidden items-center gap-1 text-xs text-ink-secondary"><input type="checkbox" data-field="remove_image" data-label="{{ __('catalog::products.variants.remove_image') }}" value="1" class="ui-check">{{ __('catalog::products.variants.remove_image') }}</label>
                                </span>
                            </td>
                            <td class="px-3 py-2"><input type="checkbox" role="switch" data-field="is_active" data-label="{{ __('catalog::products.variants.active') }}" value="1" class="ui-switch" checked aria-label="{{ __('catalog::products.variants.active') }}"></td>
                        </tr>
                    </template>
                </x-ui::tab-panel>

                {{-- 011b Pricing & weight --}}
                <x-ui::tab-panel tabs="product-tabs" key="pricing" :active="$tab === 'pricing'" class="pt-6">
                    <x-ui::card :title="__('catalog::products.sections.pricing')">
                        <div class="grid gap-5 sm:grid-cols-2" data-pricing>
                            <x-ui::input name="price" type="number" step="0.01" min="0" inputmode="decimal" :label="__('catalog::products.fields.price')" :value="$priceValue" :helper="__('catalog::products.fields.price_help')" required dir="ltr" data-validate data-price />
                            <x-ui::input name="compare_at_price" type="number" step="0.01" min="0" inputmode="decimal" :label="__('catalog::products.fields.compare_at_price')" :value="$compareValue" :helper="__('catalog::products.fields.compare_at_help')" dir="ltr" data-validate />
                            <x-ui::input name="cost_price" type="number" step="0.01" min="0" inputmode="decimal" :label="__('catalog::products.fields.cost_price')" :value="$costValue" required dir="ltr" data-validate data-cost />
                            <x-ui::input name="margin" :label="__('catalog::products.fields.margin')" :value="$isNew || $product->marginPercentage() === null ? '' : $product->marginPercentage().'%'" readonly dir="ltr" data-margin data-unsaved-ignore />
                            <x-ui::input name="weight_grams" type="number" step="1" min="0" :label="__('catalog::products.fields.weight_grams')" :value="$product->weight_grams" :helper="__('catalog::products.fields.weight_help')" dir="ltr" data-validate />
                            <x-ui::select name="is_taxable" :label="__('catalog::products.fields.tax_class')" :options="['1' => __('catalog::products.fields.tax_standard'), '0' => __('catalog::products.fields.tax_exempt')]" :selected="$product->is_taxable ? '1' : '0'" :search="false" />
                        </div>
                    </x-ui::card>
                </x-ui::tab-panel>

                {{-- 011d Media --}}
                <x-ui::tab-panel tabs="product-tabs" key="media" :active="$tab === 'media'" class="pt-6">
                    <x-ui::card :title="__('catalog::products.sections.media')">
                        <div class="grid gap-4">
                            <x-ui::image-upload name="images" :max="\App\Modules\Catalog\Http\Requests\Admin\ProductRequest::MAX_IMAGES" accept="image/jpeg,image/png,image/webp" :images="collect($gallery)->map(fn ($item) => ['id' => $item['id'], 'url' => $item['url']])->all()" :helper="__('catalog::products.fields.images_help')" :label="__('catalog::products.fields.images')" />
                            <p class="flex items-center gap-2 rounded-control bg-info-bg px-3 py-2.5 text-[13px] text-info" role="status">
                                <x-ui::icon name="info" :size="16" class="shrink-0" />
                                {{ trans_choice('catalog::products.media_state', count($gallery), [
                                    'count' => count($gallery),
                                    'done' => ($statusCounts['done'] ?? 0) + ($statusCounts['skipped'] ?? 0),
                                    'pending' => $statusCounts['pending'] ?? 0,
                                    'failed' => $statusCounts['failed'] ?? 0,
                                ]) }}
                            </p>
                        </div>
                    </x-ui::card>
                </x-ui::tab-panel>

                {{-- 011e Countries & shipping --}}
                <x-ui::tab-panel tabs="product-tabs" key="countries" :active="$tab === 'countries'" class="grid gap-6 pt-6">
                    <x-ui::card :title="__('catalog::products.sections.countries')" :subtitle="__('catalog::products.sections.countries_hint')">
                        <div class="grid gap-4" data-country-mode-scope>
                            <x-ui::radio-group name="country_mode" :label="__('catalog::products.fields.country_mode')" :options="$countryModes" :value="$countryMode->value" :inline="false" data-country-mode />
                            <div data-country-select @if ($countryMode !== \App\Modules\Catalog\Enums\CountryAvailability::Selected) hidden @endif>
                                <x-ui::select name="country_ids" multiple :label="__('catalog::products.fields.country_ids')" :options="$countryOptions" :selected="$countryMode === \App\Modules\Catalog\Enums\CountryAvailability::Selected ? (array) $product->allowed_country_ids : []" :placeholder="__('catalog::products.fields.country_ids')" />
                            </div>
                        </div>
                    </x-ui::card>
                    <x-ui::card :title="__('catalog::products.sections.shipping')">
                        <div class="grid gap-5">
                            <div class="grid gap-5 sm:grid-cols-2 sm:items-end">
                                <x-ui::select name="delivery_scope" :label="__('catalog::products.fields.delivery_scope')" :options="$deliveryScopes" :selected="$product->delivery_scope?->value ?? 'all_zones'" :search="false" required />
                                <x-ui::switch name="requires_shipping" class="pb-3" :label="__('catalog::products.fields.requires_shipping')" :checked="$isNew || $product->requires_shipping" />
                            </div>
                            <div class="grid gap-2">
                                <h3 class="text-sm font-medium text-ink">{{ __('catalog::products.fixed_shipping') }}</h3>
                                @if ($fixedShipping === [])
                                    <p class="text-[13px] text-ink-secondary">{{ __('catalog::products.fixed_shipping_empty') }}</p>
                                @else
                                    <ul class="grid gap-1 text-[13px]">
                                        @foreach ($fixedShipping as $countryId => $minor)
                                            <li class="flex justify-between gap-3"><span>{{ $countryOptions[(int) $countryId] ?? $countryId }}</span><span dir="ltr" class="font-medium">{{ \App\Shared\Money\Money::ofMinor((int) $minor)->format() }}</span></li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>
                        </div>
                    </x-ui::card>
                </x-ui::tab-panel>

                {{-- 011f Categories, labels, badges --}}
                <x-ui::tab-panel tabs="product-tabs" key="taxonomy" :active="$tab === 'taxonomy'" class="pt-6">
                    <x-ui::card :title="__('catalog::products.sections.taxonomy')">
                        <div class="grid gap-5">
                            <x-ui::select name="category_ids" multiple :label="__('catalog::products.fields.category_ids')" :options="$categoryOptions" :selected="$isNew ? [] : $product->categories->pluck('id')->all()" :placeholder="__('catalog::products.fields.category_ids')" />
                            <x-ui::select name="label_ids" multiple :label="__('catalog::products.fields.label_ids')" :options="$labelOptions" :selected="$isNew ? [] : $product->labels->pluck('id')->all()" :placeholder="__('catalog::products.fields.label_ids')" />
                            <x-ui::select name="badges" multiple :label="__('catalog::products.fields.badges')" :options="$badgeOptions" :selected="$selectedBadges" :placeholder="__('catalog::products.fields.badges')" :helper="__('catalog::products.fields.badges_help')" :search="false" />
                        </div>
                    </x-ui::card>
                </x-ui::tab-panel>

                {{-- 011g Tenant assignment --}}
                <x-ui::tab-panel tabs="product-tabs" key="tenants" :active="$tab === 'tenants'" class="pt-6">
                    <x-ui::card :title="__('catalog::products.sections.tenants')" :subtitle="__('catalog::products.sections.tenants_hint')">
                        <div class="grid gap-4" data-tenant-scope>
                            <x-ui::radio-group name="assignment_scope" :label="__('catalog::products.fields.assignment_scope')" :options="$scopes" :value="$product->assignment_scope?->value ?? 'all'" data-assignment-scope />
                            <div data-tenant-select @if (($product->assignment_scope?->value ?? 'all') !== 'selected') hidden @endif>
                                <x-ui::select name="tenant_ids" multiple :label="__('catalog::products.fields.tenant_ids')" :options="$tenantOptions" :selected="array_keys($tenantOptions)" :placeholder="__('catalog::products.fields.tenant_ids')" :ajax-url="route('admin.catalog.products.tenants')" />
                            </div>
                        </div>
                    </x-ui::card>
                </x-ui::tab-panel>

                {{-- 011h Stock per branch --}}
                <x-ui::tab-panel tabs="product-tabs" key="stock" :active="$tab === 'stock'" class="pt-6">
                    <x-ui::card :title="__('catalog::products.sections.stock')" :subtitle="__('catalog::products.sections.stock_hint')">
                        <div class="grid gap-5">
                            <div class="grid gap-5 sm:grid-cols-2 sm:items-end">
                                <x-ui::switch name="manage_stock" class="pb-3" :label="__('catalog::products.fields.manage_stock')" :checked="$isNew || $product->manage_stock" />
                                <x-ui::input name="min_stock" type="number" min="0" :label="__('catalog::products.fields.min_stock')" :value="$isNew ? 0 : $product->min_stock" dir="ltr" data-validate />
                            </div>
                            @if ($builder['branches'] === [])
                                <x-ui::empty-state compact icon="building" :description="__('catalog::products.stock.no_branches')">
                                    @can('shipping.branches.manage')
                                        <x-ui::button variant="secondary" size="sm" icon="plus" :href="route('admin.shipping.branches.create')">{{ __('catalog::products.stock.add_branch') }}</x-ui::button>
                                    @endcan
                                </x-ui::empty-state>
                            @else
                                <div class="relative overflow-x-auto rounded-control border border-line">
                                    <table class="w-full text-sm">
                                        <thead class="bg-subtle text-xs text-ink-secondary">
                                            <tr data-stock-head>
                                                <th scope="col" class="px-3 py-2.5 text-start font-medium">{{ __('catalog::products.variants.variant') }}</th>
                                                @foreach ($builder['branches'] as $branch)
                                                    <th scope="col" class="px-3 py-2.5 text-end font-medium">{{ $branch['name'] }}</th>
                                                @endforeach
                                                <th scope="col" class="px-3 py-2.5 text-end font-medium">{{ __('catalog::products.stock.total') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody data-stock-rows class="divide-y divide-line" data-label-product="{{ __('catalog::products.stock.product') }}"></tbody>
                                    </table>
                                </div>
                            @endif
                        </div>
                    </x-ui::card>
                </x-ui::tab-panel>
            </x-ui::tabs>

            <x-ui::card :title="__('catalog::products.sections.status')" class="xl:sticky xl:top-24">
                <div class="grid gap-4">
                    <x-ui::select name="status" :label="__('catalog::products.fields.status')" :options="$statuses" :selected="$product->status?->value ?? 'draft'" :search="false" required />
                    @unless ($isNew)
                        <dl class="grid gap-2 text-[13px]">
                            <div class="flex justify-between gap-3"><dt class="text-ink-secondary">{{ __('catalog::products.columns.stock') }}</dt><dd class="font-medium text-ink" dir="ltr">{{ number_format($product->stock) }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-ink-secondary">{{ __('catalog::products.columns.price') }}</dt><dd class="font-medium text-ink" dir="ltr">{{ $product->costBasis()->format() }}</dd></div>
                        </dl>
                        <x-ui::button variant="secondary" icon="eye" data-remote-modal="price-list-modal" :data-url="route('admin.catalog.products.prices', $product)">{{ __('catalog::products.price_list') }}</x-ui::button>
                    @endunless
                </div>
            </x-ui::card>
        </div>

        <div data-unsaved-bar hidden class="sticky bottom-0 z-20 -mx-4 flex flex-wrap items-center justify-between gap-3 border-t border-line bg-surface px-4 py-3 shadow-overlay sm:-mx-6 sm:px-6 xl:-mx-8 xl:px-8">
            <p class="flex items-center gap-2 text-sm font-medium text-ink"><span class="size-2 rounded-full bg-warning" aria-hidden="true"></span>{{ __('catalog::products.unsaved') }}</p>
            <div class="flex gap-3">
                <x-ui::button variant="secondary" data-unsaved-discard>{{ __('catalog::products.discard') }}</x-ui::button>
                <x-ui::button type="submit" icon="check">{{ __('catalog::products.save') }}</x-ui::button>
            </div>
        </div>
    </form>

    @unless ($isNew)
        <x-ui::modal id="price-list-modal" :title="__('catalog::products.price_list')" :subtitle="__('catalog::products.prices.subtitle')" size="lg">
            @include('catalog::admin.partials.remote-states', ['error' => __('catalog::products.prices.load_error')])
            <x-slot:footer>
                <x-ui::button variant="secondary" data-overlay-close>{{ __('catalog::products.prices.close') }}</x-ui::button>
            </x-slot:footer>
        </x-ui::modal>
    @endunless
</x-layouts.panel>
