{{--
    Vendor › Sort products & categories (Vendor Panel (new screens) 83:102998): Products tab
    (VEN-014) = drag & drop list of every store product (imported + own); Categories tab
    (VEN-017c) = drag & drop order of the store's categories (platform + own, decision #74).
    Keyboard moves, Save order.
    Data: $tab (products|categories), $views (list<StoreProductView>), $prices (id => Money|null),
    $categoryRows (list<StoreCategoryRow>, categories tab).
--}}
@php
    $tab ??= 'products';
    $categoryRows ??= [];
    $staff = auth('vendor')->user();
    $canManage = $tab === 'categories' ? (bool) $staff?->can('catalog.categories.manage') : (bool) $staff?->can('catalog.products.manage');
    $hasRows = $tab === 'categories' ? $categoryRows !== [] : $views !== [];
    $tabs = array_filter([
        'products' => $staff?->can('catalog.products.manage') ? ['label' => __('catalog::taxonomy.sort.tabs.products'), 'href' => vendor_route('catalog.products.sort')] : null,
        'categories' => $staff?->can('catalog.categories.view') ? ['label' => __('catalog::taxonomy.sort.tabs.categories'), 'href' => vendor_route('catalog.categories.sort')] : null,
    ]);
    $number = fn (int $value) => \Illuminate\Support\Number::format($value, locale: \App\Shared\Support\Locale::numberLocale());
@endphp
<x-vendor::layout :title="__('catalog::vendor.sort.title')" page="vendor-catalog">
    <x-ui::page-header
        :title="__('catalog::vendor.sort.title')"
        :subtitle="__('catalog::vendor.sort.subtitle')"
        :breadcrumbs="[__('catalog::vendor.products.title') => vendor_route('catalog.products.index'), __('catalog::vendor.sort.crumb') => null]"
    >
        @if ($canManage && $hasRows)
            <x-slot:actions>
                <x-ui::button type="button" icon="check" data-sort-save>{{ __('catalog::vendor.sort.save') }}</x-ui::button>
            </x-slot:actions>
        @endif
        @if (count($tabs) > 1)
            <x-slot:tabs>
                <x-ui::tabs id="sort-tabs" :active="$tab" :label="__('catalog::vendor.sort.title')" :items="$tabs" />
            </x-slot:tabs>
        @endif
    </x-ui::page-header>

    @if ($tab === 'categories')
        <section class="grid gap-3 rounded-card border border-line bg-surface p-4 shadow-card" data-product-sort data-sort-url="{{ vendor_route('catalog.categories.sort.update') }}" @if (! $canManage) data-readonly @endif>
            <h2 class="text-base font-semibold text-ink">{{ __('catalog::taxonomy.sort.categories_title') }}</h2>
            @if ($categoryRows === [])
                <x-ui::empty-state icon="layers" :title="__('catalog::taxonomy.sort.categories_empty_title')" :description="__('catalog::taxonomy.sort.categories_empty_description')">
                    @if ($staff?->can('catalog.categories.manage'))
                        <x-ui::button :href="vendor_route('catalog.categories.create')" icon="plus">{{ __('catalog::taxonomy.categories.add') }}</x-ui::button>
                    @endif
                </x-ui::empty-state>
            @else
                <p class="text-xs text-ink-muted" data-sort-dirty hidden>{{ __('catalog::vendor.sort.unsaved') }}</p>
                <ol class="grid gap-2" data-sort-list>
                    @foreach ($categoryRows as $index => $row)
                        <li class="vnd-sort-item flex items-center gap-3 rounded-control border border-line bg-surface px-3 py-2" data-sort-item data-id="{{ $row->ref->key() }}" tabindex="0" aria-label="{{ $row->name }}">
                            <button type="button" class="cursor-grab text-ink-muted" data-drag-handle aria-label="{{ __('catalog::vendor.sort.drag', ['name' => $row->name]) }}" @disabled(! $canManage)><x-ui::icon name="menu" :size="18" /></button>
                            <span class="w-6 text-xs text-ink-muted" data-sort-position dir="ltr">{{ $index + 1 }}</span>
                            <span class="grid min-w-0 flex-1">
                                <span class="truncate text-sm font-medium text-ink">{{ $row->path }}</span>
                                <span class="text-xs text-ink-secondary">{{ trans_choice('catalog::taxonomy.categories.products_count', $row->productCount(), ['count' => $number($row->productCount())]) }}</span>
                            </span>
                            <x-ui::badge :tone="$row->isOwn() ? 'brand' : 'neutral'">{{ __('catalog::taxonomy.source.'.$row->source()->value) }}</x-ui::badge>
                            @if (! $row->visible)
                                <x-ui::badge tone="warning" dot>{{ __('catalog::taxonomy.categories.hidden_badge') }}</x-ui::badge>
                            @endif
                            @if ($canManage)
                                <span class="flex gap-1">
                                    <x-ui::icon-button icon="chevron-up" size="sm" :label="__('catalog::vendor.sort.up')" data-sort-move="up" />
                                    <x-ui::icon-button icon="chevron-down" size="sm" :label="__('catalog::vendor.sort.down')" data-sort-move="down" />
                                </span>
                            @endif
                        </li>
                    @endforeach
                </ol>
            @endif
        </section>
    @else
        <section class="grid gap-3 rounded-card border border-line bg-surface p-4 shadow-card" data-product-sort data-sort-url="{{ vendor_route('catalog.products.sort.update') }}" @if (! $canManage) data-readonly @endif>
            <h2 class="text-base font-semibold text-ink">{{ __('catalog::vendor.sort.list_title') }}</h2>
            @if ($views === [])
                <x-ui::empty-state icon="sort" :title="__('catalog::vendor.sort.empty_title')" :description="__('catalog::vendor.sort.empty_description')">
                    <x-ui::button :href="vendor_route('catalog.products.new')" icon="plus">{{ __('catalog::vendor.new.browse') }}</x-ui::button>
                </x-ui::empty-state>
            @else
                <p class="text-xs text-ink-muted" data-sort-dirty hidden>{{ __('catalog::vendor.sort.unsaved') }}</p>
                <ol class="grid gap-2" data-sort-list>
                    @foreach ($views as $index => $view)
                        <li class="vnd-sort-item flex items-center gap-3 rounded-control border border-line bg-surface px-3 py-2" data-sort-item data-id="{{ $view->id() }}" tabindex="0" aria-label="{{ $view->name }}">
                            <button type="button" class="cursor-grab text-ink-muted" data-drag-handle aria-label="{{ __('catalog::vendor.sort.drag', ['name' => $view->name]) }}" @disabled(! $canManage)><x-ui::icon name="menu" :size="18" /></button>
                            <span class="w-6 text-xs text-ink-muted" data-sort-position dir="ltr">{{ $index + 1 }}</span>
                            @if ($view->primaryImageUrl())
                                <img src="{{ $view->primaryImageUrl() }}" alt="" class="size-9 rounded-chip object-contain bg-media" loading="lazy">
                            @else
                                <span class="grid size-9 place-items-center rounded-chip bg-primary-subtle text-ink-brand"><x-ui::icon name="box" :size="16" /></span>
                            @endif
                            <span class="grid min-w-0 flex-1">
                                <span class="truncate text-sm font-medium text-ink">{{ $view->name }}</span>
                                <span class="text-xs text-ink-secondary" dir="ltr">{{ $prices[$view->id()]?->format() ?? '—' }}</span>
                            </span>
                            @if ($view->isOwn())
                                <x-ui::badge tone="info">{{ __('catalog::enums.store_product_source.own') }}</x-ui::badge>
                            @endif
                            @if ($view->product->is_featured)
                                <x-ui::badge tone="brand" dot>{{ __('catalog::vendor.products.featured') }}</x-ui::badge>
                            @endif
                            @if ($canManage)
                                <span class="flex gap-1">
                                    <x-ui::icon-button icon="chevron-up" size="sm" :label="__('catalog::vendor.sort.up')" data-sort-move="up" />
                                    <x-ui::icon-button icon="chevron-down" size="sm" :label="__('catalog::vendor.sort.down')" data-sort-move="down" />
                                </span>
                            @endif
                        </li>
                    @endforeach
                </ol>
            @endif
        </section>
    @endif
</x-vendor::layout>
