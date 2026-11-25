{{--
    Admin › Catalog › Categories › Sort (ADM-014b / 014c, Figma Admin Panel 65:24654):
    "Category order" tab — the whole tree, drag to reorder or nest, then Save order;
    "Products in category" tab — categories on the side, the category's products sorted
    by drag and drop (or the arrow keys on a handle), then Save order.
--}}
@php
    $firstId = array_key_first($flat);
    $title = __('catalog::categories.sort_page.title');
@endphp
<x-layouts.panel surface="admin" :title="$title" data-page="admin-catalog">
    <div class="grid gap-6">
        <x-ui::page-header
            :title="$title"
            :breadcrumbs="[__('navigation.sections.catalog') => null, __('catalog::categories.title') => route('admin.catalog.categories.index'), __('catalog::categories.sort') => null]"
        >
            @if ($tab === 'categories' ? $tree !== [] : $products->isNotEmpty())
                <x-slot:actions>
                    <x-ui::button icon="check" data-sort-save>{{ __('catalog::categories.sort_page.save') }}</x-ui::button>
                </x-slot:actions>
            @endif
            <x-slot:tabs>
                <x-ui::tabs id="category-sort-tabs" :active="$tab" :label="$title" :items="array_filter([
                    'categories' => ['label' => __('catalog::categories.sort_page.tab_categories'), 'href' => route('admin.catalog.categories.sort')],
                    'products' => $firstId !== null ? ['label' => __('catalog::categories.sort_page.tab_products'), 'href' => route('admin.catalog.categories.products', $current ?? $firstId)] : null,
                ])" />
            </x-slot:tabs>
        </x-ui::page-header>

        @if ($tab === 'categories')
            <x-ui::card :title="__('catalog::categories.tree')" :subtitle="__('catalog::categories.sort_page.tree_hint')">
                @if ($tree === [])
                    <x-ui::empty-state icon="layers" :title="__('catalog::categories.empty_title')" :description="__('catalog::categories.empty_description')">
                        <x-ui::button icon="plus" :href="route('admin.catalog.categories.create')">{{ __('catalog::categories.add') }}</x-ui::button>
                    </x-ui::empty-state>
                @else
                    <div data-category-tree data-tree-url="{{ route('admin.catalog.categories.tree') }}" data-sort-scope class="relative -mx-1 aria-busy:opacity-60">
                        @include('catalog::admin.categories._nodes', ['nodes' => $tree, 'depth' => 1, 'editable' => true, 'details' => false])
                    </div>
                    <p data-sort-dirty hidden class="mt-4 text-[13px] text-warning" role="status">{{ __('catalog::categories.sort_page.unsaved') }}</p>
                @endif
            </x-ui::card>
        @else
            <div class="grid items-start gap-6 lg:grid-cols-[320px_minmax(0,1fr)]">
                <x-ui::card :title="__('catalog::categories.sort_page.categories')">
                    <nav aria-label="{{ __('catalog::categories.sort_page.categories') }}" class="grid max-h-[70vh] gap-2 overflow-y-auto">
                        @foreach ($flat as $id => $row)
                            <a href="{{ route('admin.catalog.categories.products', $id) }}" @if ($current?->id === $id) aria-current="page" @endif
                                class="flex items-center gap-3 rounded-control border border-line px-3 py-2.5 text-sm transition-colors hover:bg-subtle aria-[current=page]:border-primary aria-[current=page]:bg-primary-subtle">
                                <span class="min-w-0 flex-1">
                                    <span @class(['block truncate text-ink', 'font-medium' => $row['depth'] === 1])>{{ str_repeat('— ', $row['depth'] - 1) }}{{ $row['category']->name }}</span>
                                    <span class="block text-xs text-ink-secondary" dir="ltr">{{ number_format((int) $row['category']->products_count) }}</span>
                                </span>
                            </a>
                        @endforeach
                    </nav>
                </x-ui::card>

                <x-ui::card :title="__('catalog::categories.sort_page.products_in', ['name' => $current->name])" :subtitle="__('catalog::categories.sort_page.products_hint')">
                    @if ($products->isEmpty())
                        <x-ui::empty-state icon="box" :title="__('catalog::categories.sort_page.no_products_title')" :description="__('catalog::categories.sort_page.no_products_description')" />
                    @else
                        <ol data-sortable-list data-sort-url="{{ route('admin.catalog.categories.products.order', $current) }}" data-sort-scope class="grid gap-2 aria-busy:opacity-60">
                            @foreach ($products as $product)
                                @include('catalog::admin.partials.product-row', ['product' => $product, 'subtitle' => $product->costBasis()->format(), 'remove' => null])
                            @endforeach
                        </ol>
                        <p data-sort-dirty hidden class="mt-4 text-[13px] text-warning" role="status">{{ __('catalog::categories.sort_page.unsaved') }}</p>
                    @endif
                </x-ui::card>
            </div>
        @endif
    </div>
</x-layouts.panel>
