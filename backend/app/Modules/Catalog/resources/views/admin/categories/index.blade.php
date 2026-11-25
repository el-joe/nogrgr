{{--
    Admin › Catalog › Categories (ADM-014, Figma Admin Panel 65:22996): category tree
    (drag rows to nest or reorder — saved at once; arrow keys on a handle do the same),
    expand / collapse, detail panel of the selected category (featured switch, edit,
    products order) and delete. Sort opens ADM-014b.
--}}
@php
    $canManage = (bool) auth('admin')->user()?->can('catalog.categories.manage');
    $flatten = function (array $nodes) use (&$flatten): array {
        $rows = [];
        foreach ($nodes as $node) {
            $rows[] = $node['category'];
            array_push($rows, ...$flatten($node['children']));
        }

        return $rows;
    };
    $all = $flatten($tree);
@endphp
<x-layouts.panel surface="admin" :title="__('catalog::categories.title')" data-page="admin-catalog">
    <div class="grid gap-6">
        <x-ui::page-header
            :title="__('catalog::categories.title')"
            :subtitle="trans_choice('catalog::categories.subtitle', $total, ['count' => number_format($total)])"
            :breadcrumbs="[__('navigation.sections.catalog') => null, __('catalog::categories.title') => null]"
        >
            @if ($canManage)
                <x-slot:actions>
                    @if ($total > 0)
                        <x-ui::button variant="secondary" icon="sort" :href="route('admin.catalog.categories.sort')">{{ __('catalog::categories.sort') }}</x-ui::button>
                    @endif
                    <x-ui::button icon="plus" :href="route('admin.catalog.categories.create')">{{ __('catalog::categories.add') }}</x-ui::button>
                </x-slot:actions>
            @endif
        </x-ui::page-header>

        @if ($tree === [])
            <x-ui::card>
                <x-ui::empty-state icon="layers" :title="__('catalog::categories.empty_title')" :description="__('catalog::categories.empty_description')">
                    @if ($canManage)
                        <x-ui::button icon="plus" :href="route('admin.catalog.categories.create')">{{ __('catalog::categories.add') }}</x-ui::button>
                    @endif
                </x-ui::empty-state>
            </x-ui::card>
        @else
            <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
                <x-ui::card :title="__('catalog::categories.tree')" id="category-tree-card">
                    <x-slot:actions>
                        <button type="button" data-category-expand-all data-label-expand="{{ __('catalog::categories.expand_all') }}" data-label-collapse="{{ __('catalog::categories.collapse_all') }}" aria-pressed="true" class="text-[13px] font-medium text-ink-brand hover:underline">{{ __('catalog::categories.collapse_all') }}</button>
                    </x-slot:actions>
                    <div data-category-tree @if ($canManage) data-tree-url="{{ route('admin.catalog.categories.tree') }}" data-tree-autosave @endif class="relative -mx-1 aria-busy:opacity-60">
                        @include('catalog::admin.categories._nodes', ['nodes' => $tree, 'depth' => 1, 'editable' => true, 'details' => true])
                    </div>
                </x-ui::card>

                <x-ui::card class="lg:sticky lg:top-24">
                    <p data-category-detail-empty class="py-6 text-center text-sm text-ink-secondary">{{ __('catalog::categories.select_hint') }}</p>
                    @foreach ($all as $category)
                        <section data-category-detail="{{ $category->id }}" hidden aria-labelledby="category-detail-{{ $category->id }}" class="grid gap-4">
                            <header>
                                <h2 id="category-detail-{{ $category->id }}" class="text-base font-semibold text-ink">{{ $category->name }}</h2>
                                <p class="text-xs text-ink-secondary">{{ __('catalog::categories.selected') }}</p>
                            </header>
                            @include('catalog::admin.partials.thumb', ['url' => $category->thumbnailUrl(), 'webp' => $category->thumbnailWebpUrl(), 'avif' => $category->thumbnailAvifUrl(), 'size' => 'aspect-[2/1] w-full'])
                            <dl class="grid gap-3 text-[13px]">
                                <div class="flex items-center justify-between gap-3"><dt class="text-ink-secondary">{{ __('catalog::categories.detail.slug') }}</dt><dd class="truncate font-medium text-ink" dir="ltr">{{ $category->slug }}</dd></div>
                                <div class="flex items-center justify-between gap-3"><dt class="text-ink-secondary">{{ __('catalog::categories.detail.products') }}</dt><dd class="font-medium text-ink" dir="ltr">{{ number_format((int) $category->products_count) }}</dd></div>
                                <div class="flex items-center justify-between gap-3">
                                    <dt class="text-ink-secondary">{{ __('catalog::categories.detail.featured') }}</dt>
                                    <dd>
                                        @if ($canManage)
                                            <x-ui::switch :name="'is_featured_'.$category->id" field="is_featured" :url="route('admin.catalog.categories.featured', $category)" :checked="$category->is_featured" :aria-label="__('catalog::categories.toggle_featured', ['name' => $category->name])" />
                                        @else
                                            <x-ui::badge :tone="$category->is_featured ? 'success' : 'neutral'">{{ $category->is_featured ? __('catalog::categories.detail.yes') : __('catalog::categories.detail.no') }}</x-ui::badge>
                                        @endif
                                    </dd>
                                </div>
                                <div class="flex items-center justify-between gap-3"><dt class="text-ink-secondary">{{ __('catalog::categories.detail.translations') }}</dt><dd class="font-medium uppercase text-ink" dir="ltr">{{ implode(' · ', $category->translatedLocales()) }}</dd></div>
                                <div class="flex items-center justify-between gap-3"><dt class="text-ink-secondary">{{ __('catalog::categories.detail.sort_position') }}</dt><dd class="font-medium text-ink" dir="ltr">{{ $category->sort_order + 1 }}</dd></div>
                            </dl>
                            @if ($canManage)
                                <div class="flex flex-wrap items-center gap-3">
                                    <x-ui::button variant="secondary" icon="edit" :href="route('admin.catalog.categories.edit', $category)">{{ __('catalog::common.edit') }}</x-ui::button>
                                    <x-ui::button variant="ghost" icon="sort" :href="route('admin.catalog.categories.products', $category)">{{ __('catalog::categories.products_order') }}</x-ui::button>
                                </div>
                            @endif
                        </section>
                    @endforeach
                </x-ui::card>
            </div>
        @endif
    </div>
</x-layouts.panel>
