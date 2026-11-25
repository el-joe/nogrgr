{{--
    Vendor › Product sort (VEN-014, Vendor Panel (new screens) 83:102998): drag & drop list
    of every store product (imported + own), keyboard moves, Save order. The Categories tab
    of the frame belongs to VEN-017c (category sort).
    Data: $views (list<StoreProductView>), $prices (id => Money|null).
--}}
@php
    $canManage = (bool) auth('vendor')->user()?->can('catalog.products.manage');
@endphp
<x-vendor::layout :title="__('catalog::vendor.sort.title')" page="vendor-catalog">
    <x-ui::page-header
        :title="__('catalog::vendor.sort.title')"
        :subtitle="__('catalog::vendor.sort.subtitle')"
        :breadcrumbs="[__('catalog::vendor.products.title') => vendor_route('catalog.products.index'), __('catalog::vendor.sort.crumb') => null]"
    >
        @if ($canManage && $views !== [])
            <x-slot:actions>
                <x-ui::button type="button" icon="check" data-sort-save>{{ __('catalog::vendor.sort.save') }}</x-ui::button>
            </x-slot:actions>
        @endif
    </x-ui::page-header>

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
                            <img src="{{ $view->primaryImageUrl() }}" alt="" class="size-9 rounded-chip object-cover" loading="lazy">
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
</x-vendor::layout>
