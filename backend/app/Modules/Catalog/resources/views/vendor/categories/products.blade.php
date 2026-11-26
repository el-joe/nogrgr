{{--
    Vendor › Category inner page (VEN-017a, Tenant Panel Layout 12:14465, mobile 12:22248):
    category title, Add product / Add category, Products | Order tabs. Products = the card
    grid of the category (remove from category per card); Order = drag & drop product order
    of the category (Save order). Add product opens the product picker modal.
    Data: $row (StoreCategoryRow), $tab (products|order), $cards (paginator|null), $sortRows.
--}}
@php
    $staff = auth('vendor')->user();
    $canManage = (bool) $staff?->can('catalog.categories.manage');
    $key = $row->ref->routeKey();
    $tabs = [
        'products' => ['label' => __('catalog::taxonomy.products.tabs.products'), 'href' => vendor_route('catalog.categories.products', ['category' => $key]), 'count' => $row->productCount()],
        'order' => ['label' => __('catalog::taxonomy.products.tabs.order'), 'href' => vendor_route('catalog.categories.products', ['category' => $key, 'tab' => 'order'])],
    ];
@endphp
<x-vendor::layout :title="$row->name" page="vendor-catalog">
    <nav aria-label="{{ __('ui.breadcrumbs') }}" class="flex items-center gap-1.5 text-[13px] text-ink-secondary">
        <a href="{{ vendor_route('catalog.categories.index') }}" class="hover:text-ink">{{ __('catalog::taxonomy.categories.title') }}</a>
        <x-ui::icon name="chevron-right" :size="14" data-flip />
        <span class="font-medium text-ink">{{ $row->name }}</span>
    </nav>

    <header class="flex flex-wrap items-start justify-between gap-4" data-reveal="fade-up">
        <div class="grid min-w-0 gap-1">
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="text-[26px] font-bold leading-[1.5] text-ink sm:text-[32px]">{{ $row->name }}</h1>
                <x-ui::badge :tone="$row->isOwn() ? 'brand' : 'neutral'">{{ __('catalog::taxonomy.source.'.$row->source()->value) }}</x-ui::badge>
                @if (! $row->visible)
                    <x-ui::badge tone="warning" dot>{{ __('catalog::taxonomy.categories.hidden_badge') }}</x-ui::badge>
                @endif
            </div>
            <p class="text-sm text-ink-muted">{{ $row->isOwn() ? __('catalog::taxonomy.products.subtitle') : __('catalog::taxonomy.categories.central_note') }}</p>
        </div>
        @if ($canManage)
            <div class="flex w-full flex-wrap gap-3 sm:w-auto">
                @if ($tab === 'order' && $sortRows !== [])
                    <button type="button" class="vnd-cta vnd-cta-primary flex-1 sm:w-[183px] sm:flex-none" data-sort-save><x-ui::icon name="check" :size="20" />{{ __('catalog::vendor.sort.save') }}</button>
                @else
                    <button type="button" class="vnd-cta vnd-cta-primary flex-1 sm:w-[183px] sm:flex-none" data-overlay-open="category-products-modal"><x-ui::icon name="plus" :size="20" />{{ __('catalog::taxonomy.products.add') }}</button>
                @endif
                <a href="{{ vendor_route('catalog.categories.create') }}" class="vnd-cta vnd-cta-outline flex-1 sm:w-[183px] sm:flex-none"><x-ui::icon name="plus" :size="20" />{{ __('catalog::taxonomy.categories.add') }}</a>
            </div>
        @endif
    </header>

    <x-ui::tabs id="category-tabs" variant="segmented" :active="$tab" :label="$row->name" :items="$tabs" />

    @if ($row->productCount() === 0)
        <x-ui::card>
            <x-ui::empty-state icon="box" :title="__('catalog::taxonomy.products.empty_title')" :description="__('catalog::taxonomy.products.empty_description')">
                @if ($canManage)
                    <x-ui::button icon="plus" data-overlay-open="category-products-modal">{{ __('catalog::taxonomy.products.add') }}</x-ui::button>
                @endif
            </x-ui::empty-state>
        </x-ui::card>
    @elseif ($tab === 'order')
        @include('catalog::vendor.categories._sort-list', [
            'rows' => $sortRows,
            'sortUrl' => vendor_route('catalog.categories.products.sort', ['category' => $key]),
            'canManage' => $canManage,
            'title' => __('catalog::taxonomy.products.order_title'),
            'hint' => __('catalog::taxonomy.products.order_hint'),
        ])
    @else
        <div class="grid grid-cols-[repeat(auto-fill,minmax(min(100%,300px),1fr))] gap-6" data-reveal-stagger>
            @foreach ($cards as $card)
                @include('catalog::vendor.categories._card', ['card' => $card] + ($canManage ? ['removeUrl' => vendor_route('catalog.categories.products.detach', ['category' => $key, 'product' => $card['product']->id])] : []))
            @endforeach
        </div>
        @if ($cards->hasPages())
            <x-ui::pagination :paginator="$cards" />
        @endif
    @endif

    @if ($canManage)
        <x-ui::modal id="category-products-modal" :title="__('catalog::taxonomy.products.add_title', ['name' => $row->name])">
            <form id="category-products-form" method="POST" action="{{ vendor_route('catalog.categories.products.attach', ['category' => $key]) }}" class="grid gap-4" data-ajax novalidate>
                @csrf
                <x-ui::select name="product_ids" multiple :label="__('catalog::taxonomy.products.picker')" :placeholder="__('catalog::taxonomy.products.picker_placeholder')" :ajax-url="vendor_route('catalog.categories.search')" required />
            </form>
            <x-slot:footer>
                <x-ui::button variant="secondary" data-overlay-close>{{ __('catalog::taxonomy.products.cancel') }}</x-ui::button>
                <x-ui::button type="submit" form="category-products-form" icon="plus">{{ __('catalog::taxonomy.products.save') }}</x-ui::button>
            </x-slot:footer>
        </x-ui::modal>
    @endif

    @include('catalog::vendor.products._modals', ['canManage' => (bool) $staff?->can('catalog.products.manage')])
</x-vendor::layout>
