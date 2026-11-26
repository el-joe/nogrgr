{{--
    Vendor › Product edit (VEN-012, flow 83:100772 / 83:101628, aligned frame 184:29780) in the
    client "Add Own Product" form language 12:17411 (decision #101): Bold 32 title, Back to
    products outline + Save product primary in the head, r24 section cards, filled inputs.
    The vendor edits only store business data here: markup per variant × country (012a,
    live server preview), store category / badge placement (012d) and active / featured.
    Central data — content, images, stock, weight, variants — is read-only (012c stock &
    weight, 012e central snapshot); changes go through "Request edit" (VEN-013a) and an
    approved request writes the store override. Content & translations (012b): the texts per
    store language + "Translate with AI" (Ai module partials, preview then save into the
    store override in the chosen languages only).
    Data: $product, $view (StoreProductView), $rows, $summary, $categoryOptions, $badgeOptions, $storeCategoryOptions, $canManage, $tab.
--}}
@php
    $central = $view->central;
    $tabs = [
        'pricing' => __('catalog::vendor.edit.tabs.pricing'),
        'content' => __('ai::product_translation.tab'),
        'stock' => __('catalog::vendor.edit.tabs.stock'),
        'categories' => __('catalog::vendor.edit.tabs.categories'),
        'snapshot' => __('catalog::vendor.edit.tabs.snapshot'),
    ];
    $variantKey = fn ($row) => $row->variantId !== null ? (string) $row->variantId : 'product';
    $overridden = array_keys($view->override?->approvals ?? []);
@endphp
<x-vendor::layout :title="$view->name" page="vendor-catalog">
    <header class="flex flex-wrap items-start justify-between gap-4" data-reveal="fade-up">
        <div class="grid min-w-0 gap-1">
            <div class="flex flex-wrap items-center gap-3">
                <h1 class="ui-page-title text-[26px] font-bold leading-[1.5] text-ink sm:text-[32px]">{{ $view->name }}</h1>
                @if (! $view->available)
                    <x-ui::badge tone="danger" dot>{{ __('catalog::vendor.products.unavailable') }}</x-ui::badge>
                @elseif ($product->is_active)
                    <x-ui::badge tone="success" dot>{{ __('catalog::vendor.edit.published') }}</x-ui::badge>
                @else
                    <x-ui::badge tone="neutral" dot>{{ __('catalog::vendor.products.hidden') }}</x-ui::badge>
                @endif
            </div>
            <p class="ui-page-subtitle text-sm text-ink-muted">{{ __('catalog::vendor.edit.subtitle') }}</p>
        </div>
        <div class="flex w-full flex-wrap gap-3 sm:w-auto">
            <x-ui::button variant="secondary" size="lg" icon="chevron-left" :href="vendor_route('catalog.products.index')" class="flex-1 sm:flex-none">{{ __('catalog::vendor.new.back') }}</x-ui::button>
            @if ($canManage)
                <x-ui::button variant="secondary" size="lg" icon="edit" class="flex-1 sm:flex-none" data-remote-modal="edit-request-modal" :data-url="vendor_route('catalog.edit-requests.form', ['product' => $product->id])">{{ __('catalog::vendor.edit.request_edit') }}</x-ui::button>
                @if (auth('vendor')->user()?->can('flash-sales.manage'))
                    <x-ui::button variant="secondary" size="lg" icon="zap" class="flex-1 sm:flex-none" data-remote-modal="flash-sale-attach-modal" :data-url="vendor_route('marketing.flash-sales.available-for-product', ['product' => $product->id])">{{ __('commerce::vendor_promotions.flash_sales.attach.title') }}</x-ui::button>
                @endif
                <x-ui::button type="submit" size="lg" form="product-edit-form" class="flex-1 sm:min-w-[183px] sm:flex-none">{{ __('catalog::vendor.own.save') }}</x-ui::button>
            @endif
        </div>
    </header>

    <form id="product-edit-form" method="POST" action="{{ vendor_route('catalog.products.update', ['product' => $product->id]) }}" data-ajax data-product-edit data-preview-url="{{ vendor_route('catalog.products.pricing.preview', ['product' => $product->id]) }}" novalidate>
        @csrf
        @method('PUT')
        <input type="hidden" name="tab" value="{{ $tab }}" data-active-tab>
        <input type="hidden" name="is_active" value="{{ $product->is_active ? 1 : 0 }}">
        <input type="hidden" name="is_featured" value="{{ $product->is_featured ? 1 : 0 }}">

        <x-ui::tabs id="product-edit-tabs" :items="$tabs" :active="$tab" :label="$view->name">
            {{-- 012a Pricing per variant / country --}}
            <x-ui::tab-panel tabs="product-edit-tabs" key="pricing" :active="$tab === 'pricing'" class="pt-5">
                <div class="grid items-start gap-4 lg:grid-cols-[1fr_262px]">
                    <section class="overflow-hidden rounded-[24px] bg-surface">
                        <header class="flex flex-wrap items-center justify-between gap-3 px-4 py-4 sm:px-6">
                            <h2 class="text-lg font-medium text-ink">{{ __('catalog::vendor.edit.price_per_country') }}</h2>
                            @if ($canManage && $rows !== [])
                                <x-ui::button size="sm" variant="secondary" icon="percent" data-remote-modal="price-list-modal" :data-url="vendor_route('catalog.products.price-list', ['product' => $product->id])">{{ __('catalog::vendor.edit.apply_all') }}</x-ui::button>
                            @endif
                        </header>
                        @if ($rows === [])
                            <div class="border-t border-line p-6">
                                <x-ui::empty-state compact icon="globe" :description="__('catalog::vendor.products.no_countries')" />
                            </div>
                        @else
                            <div class="relative overflow-x-auto">
                                <table class="vnd-table w-full min-w-[720px] text-sm">
                                    <thead class="bg-subtle text-[13px] text-ink-secondary">
                                        <tr>
                                            <th scope="col" class="px-4 py-3 text-start font-medium">{{ __('catalog::vendor.price_list.variant') }}</th>
                                            <th scope="col" class="px-4 py-3 text-start font-medium">{{ __('catalog::vendor.price_list.country') }}</th>
                                            <th scope="col" class="px-4 py-3 text-end font-medium">{{ __('catalog::vendor.price_list.cost') }}</th>
                                            <th scope="col" class="px-4 py-3 text-end font-medium">{{ __('catalog::vendor.price_list.markup') }}</th>
                                            <th scope="col" class="px-4 py-3 text-end font-medium">{{ __('catalog::vendor.edit.your_price') }}</th>
                                            <th scope="col" class="px-4 py-3 text-end font-medium">{{ __('catalog::vendor.edit.profit') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-line">
                                        @foreach ($rows as $index => $row)
                                            @php($prefix = 'pricing['.$variantKey($row).']['.$row->countryId.']')
                                            <tr data-price-row="{{ $index }}">
                                                <td class="px-4 py-3 text-ink">{{ $row->variantLabel ?? __('catalog::vendor.price_list.all') }}</td>
                                                <td class="px-4 py-3 text-ink">{{ $row->countryName }}</td>
                                                <td class="px-4 py-3 text-end font-medium text-ink" dir="ltr" title="{{ __('catalog::vendor.shipping.fixed') }}: {{ $row->shipping->format() }}">{{ $row->cost->format() }}</td>
                                                <td class="px-4 py-3">
                                                    <div class="ms-auto flex w-[170px] items-center gap-1">
                                                        <label class="sr-only" for="markup-type-{{ $index }}">{{ __('catalog::vendor.price_list.mode') }}</label>
                                                        <select id="markup-type-{{ $index }}" name="{{ $prefix }}[profit_type]" class="ui-control h-9 w-[78px] px-2 text-[13px]" @disabled(! $canManage) data-markup-input>
                                                            <option value="percentage" @selected($row->rule->type->value === 'percentage')>%</option>
                                                            <option value="fixed" @selected($row->rule->type->value === 'fixed')>$</option>
                                                        </select>
                                                        <label class="sr-only" for="markup-value-{{ $index }}">{{ __('catalog::vendor.price_list.markup') }}</label>
                                                        <input id="markup-value-{{ $index }}" type="number" step="0.01" min="0" name="{{ $prefix }}[profit_value]" value="{{ $row->rule->value }}" class="ui-control h-9 w-[88px] px-2 text-end text-[13px]" dir="ltr" inputmode="decimal" @disabled(! $canManage) data-markup-input>
                                                    </div>
                                                </td>
                                                <td class="px-4 py-3 text-end font-semibold text-ink" dir="ltr" data-cell="price">{{ $row->price->format() }}</td>
                                                <td class="px-4 py-3 text-end font-semibold text-ink" dir="ltr" data-cell="profit">{{ $row->profit->format() }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </section>
                    <aside class="grid gap-3 rounded-[24px] bg-surface p-4 sm:p-6" data-pricing-summary>
                        <h2 class="text-lg font-medium text-ink">{{ __('catalog::vendor.edit.summary') }}</h2>
                        <dl class="grid gap-2.5 text-sm">
                            <div class="flex justify-between gap-3"><dt class="text-ink-secondary">{{ __('catalog::vendor.edit.central_price') }}</dt><dd class="font-semibold text-ink" dir="ltr" data-summary="central">{{ $summary['central'] ?? '—' }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-ink-secondary">{{ __('catalog::vendor.edit.avg_price') }}</dt><dd class="font-semibold text-ink" dir="ltr" data-summary="average">{{ $summary['average'] ?? '—' }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-ink-secondary">{{ __('catalog::vendor.edit.platform_share') }}</dt><dd class="font-semibold text-ink" dir="ltr" data-summary="platform_share">{{ rtrim(rtrim(number_format($summary['platform_share'], 2), '0'), '.') }}%</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-ink-secondary">{{ __('catalog::vendor.edit.est_profit') }}</dt><dd class="font-semibold text-ink" dir="ltr" data-summary="profit">{{ $summary['profit'] ?? '—' }}</dd></div>
                        </dl>
                        <p class="flex gap-2 rounded-control bg-info-bg p-3 text-xs text-info"><x-ui::icon name="info" :size="16" class="shrink-0" />{{ __('catalog::vendor.edit.live_note') }}</p>
                    </aside>
                </div>
            </x-ui::tab-panel>

            {{-- 012c Stock & weight (central, read-only) --}}
            <x-ui::tab-panel tabs="product-edit-tabs" key="stock" :active="$tab === 'stock'" class="pt-5">
                <section class="grid gap-4 rounded-[24px] bg-surface p-4 sm:p-6">
                    <header class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-medium text-ink">{{ __('catalog::vendor.edit.stock_title') }}</h2>
                            <p class="text-xs text-ink-muted">{{ __('catalog::vendor.edit.stock_hint') }}</p>
                        </div>
                        @if ($canManage)
                            <x-ui::button size="sm" variant="secondary" icon="message" data-remote-modal="edit-request-modal" :data-url="vendor_route('catalog.edit-requests.form', ['product' => $product->id, 'field' => 'weight_grams'])">{{ __('catalog::vendor.edit.request_weight') }}</x-ui::button>
                        @endif
                    </header>
                    <div class="relative overflow-x-auto rounded-[12px]">
                        <table class="vnd-table w-full text-sm">
                            <thead class="bg-subtle text-[13px] text-ink-secondary">
                                <tr>
                                    <th scope="col" class="px-4 py-3 text-start font-medium">{{ __('catalog::vendor.price_list.variant') }}</th>
                                    <th scope="col" class="px-4 py-3 text-end font-medium">{{ __('catalog::vendor.edit.stock') }}</th>
                                    <th scope="col" class="px-4 py-3 text-end font-medium">{{ __('catalog::vendor.edit.weight') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-line">
                                @forelse ($view->variants as $variant)
                                    <tr>
                                        <td class="px-4 py-4 text-ink">{{ $variant->label }}</td>
                                        <td class="px-4 py-4 text-end text-ink" dir="ltr">{{ number_format($variant->stock) }}</td>
                                        <td class="px-4 py-4 text-end text-ink" dir="ltr">{{ number_format($variant->weightGrams) }}@if ($variant->weightOverridden) <x-ui::badge tone="brand">{{ __('catalog::vendor.edit.overridden') }}</x-ui::badge>@endif</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="px-4 py-4 text-ink">{{ __('catalog::vendor.price_list.all') }}</td>
                                        <td class="px-4 py-4 text-end text-ink" dir="ltr">{{ number_format($view->stock) }}</td>
                                        <td class="px-4 py-4 text-end text-ink" dir="ltr">{{ number_format($view->weightGrams) }}@if ($view->weightOverridden) <x-ui::badge tone="brand">{{ __('catalog::vendor.edit.overridden') }}</x-ui::badge>@endif</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            </x-ui::tab-panel>

            {{-- 012d Categories & badges (store placement) --}}
            <x-ui::tab-panel tabs="product-edit-tabs" key="categories" :active="$tab === 'categories'" class="pt-5">
                <section class="grid max-w-[640px] gap-4 rounded-[24px] bg-surface p-4 sm:p-6">
                    <input type="hidden" name="placement" value="1">
                    <h2 class="text-lg font-medium text-ink">{{ __('catalog::vendor.edit.categories_title') }}</h2>
                    <x-ui::select name="category_ids" multiple :label="__('catalog::vendor.edit.your_categories')" :options="$categoryOptions" :selected="$view->categoryIds" :disabled="! $canManage" :placeholder="__('catalog::vendor.edit.pick_categories')" />
                    <x-ui::select name="store_category_ids" multiple :label="__('catalog::vendor.edit.your_store_categories')" :options="$storeCategoryOptions" :selected="array_map('intval', $product->store_category_ids ?? [])" :disabled="! $canManage || $storeCategoryOptions === []" :placeholder="__('catalog::vendor.edit.pick_store_categories')" />
                    <x-ui::select name="badge_ids" multiple :label="__('catalog::vendor.edit.your_badges')" :options="$badgeOptions" :selected="$view->badgeIds" :disabled="! $canManage" :placeholder="__('catalog::vendor.edit.pick_badges')" />
                    <p class="text-xs text-ink-muted">{{ __('catalog::vendor.edit.placement_hint') }}</p>
                </section>
            </x-ui::tab-panel>

            {{-- 012b Content & translations (+ Translate with AI) --}}
            <x-ui::tab-panel tabs="product-edit-tabs" key="content" :active="$tab === 'content'" class="pt-5">
                @include('ai::vendor.product-translation.tab', ['product' => $product])
            </x-ui::tab-panel>

            {{-- 012e Central snapshot (read-only) --}}
            <x-ui::tab-panel tabs="product-edit-tabs" key="snapshot" :active="$tab === 'snapshot'" class="pt-5">
                <div class="grid items-start gap-4 lg:grid-cols-2">
                    <section class="grid gap-4 rounded-[24px] bg-surface p-4 sm:p-6">
                        <h2 class="text-lg font-medium text-ink">{{ __('catalog::vendor.edit.snapshot_title') }}</h2>
                        <dl class="grid gap-2.5 text-sm">
                            <div class="flex justify-between gap-3"><dt class="text-ink-secondary">{{ __('catalog::vendor.edit.central_title') }}</dt><dd class="text-end font-medium text-ink">{{ $central?->name ?? '—' }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-ink-secondary">{{ __('catalog::vendor.edit.central_price') }}</dt><dd class="font-medium text-ink" dir="ltr">{{ $summary['central'] ?? '—' }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-ink-secondary">{{ __('catalog::vendor.edit.weight') }}</dt><dd class="font-medium text-ink" dir="ltr">{{ number_format((int) ($central->weight_grams ?? 0)) }} g</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-ink-secondary">SKU</dt><dd class="font-medium text-ink" dir="ltr">{{ $central?->sku ?? '—' }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-ink-secondary">{{ __('catalog::vendor.edit.last_update') }}</dt><dd class="font-medium text-ink">{{ $central?->updated_at?->translatedFormat('j M Y') ?? '—' }}</dd></div>
                        </dl>
                        @if ($central?->summary)
                            <p class="text-sm leading-6 text-ink-secondary">{{ $central->summary }}</p>
                        @endif
                    </section>
                    <section class="grid gap-3 rounded-[24px] bg-surface p-4 sm:p-6">
                        <h2 class="text-lg font-medium text-ink">{{ __('catalog::vendor.edit.overrides_title') }}</h2>
                        @if ($overridden === [])
                            <p class="text-sm text-ink-secondary">{{ __('catalog::vendor.edit.no_overrides') }}</p>
                        @else
                            <ul class="grid gap-2 text-sm">
                                @foreach ($overridden as $key)
                                    @php([$field, $locale] = array_pad(explode(':', $key, 2), 2, null))
                                    <li class="flex items-center justify-between gap-3 rounded-control bg-subtle px-3 py-2">
                                        <span class="text-ink">{{ \App\Modules\Catalog\Enums\EditableProductField::tryFrom($field)?->label() ?? $field }}{{ $locale ? ' ('.strtoupper($locale).')' : '' }}</span>
                                        <span class="text-xs text-ink-secondary">ER-{{ $view->override->approvals[$key]['edit_request_id'] ?? '—' }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                        <a href="{{ vendor_route('catalog.edit-requests.index') }}" class="text-sm font-medium text-ink-brand hover:underline">{{ __('catalog::vendor.edit.requests_history') }}</a>
                    </section>
                </div>
            </x-ui::tab-panel>
        </x-ui::tabs>
    </form>

    @include('catalog::vendor.products._modals', ['canManage' => $canManage])
    @include('ai::vendor.product-translation.modal', ['product' => $product])
    @include('commerce::vendor.flash-sales._attach-modal')
</x-vendor::layout>
