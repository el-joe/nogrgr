{{--
    Vendor › Products › Categories (VEN-017, Tenant Panel Layout 12:14144): welcome header
    with Add category, Products / Own products / New / Categories pill tabs, then one
    section per category (platform + store-own, store order) with its first three product
    cards and "See all" (VEN-017a). Each section header shows the source, product count,
    the visibility switch and — for store categories — featured, edit and delete.
    Data: $categories (LengthAwarePaginator of ['row' => StoreCategoryRow, 'cards' => list]).
--}}
@php
    $staff = auth('vendor')->user();
    $canManage = (bool) $staff?->can('catalog.categories.manage');
    $actions = $canManage ? view('catalog::vendor.partials._button', ['href' => vendor_route('catalog.categories.create'), 'icon' => 'plus', 'label' => __('catalog::taxonomy.categories.add'), 'variant' => 'primary'])->render() : null;
@endphp
<x-vendor::layout :title="__('catalog::taxonomy.categories.title')" page="vendor-catalog">
    @include('catalog::vendor.partials._welcome', $actions ? ['actions' => $actions] : [])
    @include('catalog::vendor.partials._tabs', ['active' => 'categories'])

    @if ($categories->total() === 0)
        <x-ui::card>
            <x-ui::empty-state icon="layers" :title="__('catalog::taxonomy.categories.empty_title')" :description="__('catalog::taxonomy.categories.empty_description')">
                @if ($canManage)
                    <x-ui::button icon="plus" :href="vendor_route('catalog.categories.create')">{{ __('catalog::taxonomy.categories.add') }}</x-ui::button>
                @endif
            </x-ui::empty-state>
        </x-ui::card>
    @else
        @foreach ($categories as $item)
            @php
                $row = $item['row'];
                $sectionId = 'category-'.$row->ref->routeKey();
                $productsUrl = vendor_route('catalog.categories.products', ['category' => $row->ref->routeKey()]);
            @endphp
            <section class="grid gap-4" aria-labelledby="{{ $sectionId }}" data-reveal="fade-up">
                <header class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex min-w-0 flex-wrap items-center gap-2">
                        <h2 id="{{ $sectionId }}" class="text-xl font-bold text-ink sm:text-2xl">
                            <a href="{{ $productsUrl }}" class="hover:text-ink-brand">{{ $row->name }}</a>
                        </h2>
                        <x-ui::badge :tone="$row->isOwn() ? 'brand' : 'neutral'">{{ __('catalog::taxonomy.source.'.$row->source()->value) }}</x-ui::badge>
                        @if (! $row->visible)
                            <x-ui::badge tone="warning" dot>{{ __('catalog::taxonomy.categories.hidden_badge') }}</x-ui::badge>
                        @endif
                        @if ($row->featured)
                            <x-ui::badge tone="success" dot>{{ __('catalog::taxonomy.categories.featured_badge') }}</x-ui::badge>
                        @endif
                        <span class="text-sm text-ink-muted">{{ trans_choice('catalog::taxonomy.categories.products_count', $row->productCount(), ['count' => \Illuminate\Support\Number::format($row->productCount(), locale: \App\Shared\Support\Locale::numberLocale())]) }}</span>
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        @if ($canManage)
                            <x-ui::switch :name="'visible_'.$row->ref->routeKey()" field="is_active" :label="__('catalog::taxonomy.categories.visible')" :checked="$row->visible" :url="vendor_route('catalog.categories.active', ['category' => $row->ref->routeKey()])" />
                            @if ($row->isOwn())
                                <x-ui::row-actions :label="__('catalog::taxonomy.categories.actions')">
                                    <x-ui::dropdown-item icon="edit" :href="vendor_route('catalog.categories.edit', ['own' => $row->own->id])">{{ __('catalog::taxonomy.categories.edit') }}</x-ui::dropdown-item>
                                    <x-ui::dropdown-divider />
                                    <x-ui::dropdown-item icon="trash" tone="danger" :data-confirm="__('catalog::taxonomy.categories.delete_text')" :data-confirm-title="__('catalog::taxonomy.categories.delete_title', ['name' => $row->name])" data-confirm-method="DELETE" :data-confirm-url="vendor_route('catalog.categories.destroy', ['own' => $row->own->id])">{{ __('catalog::taxonomy.categories.delete') }}</x-ui::dropdown-item>
                                </x-ui::row-actions>
                            @endif
                        @endif
                        <a href="{{ $productsUrl }}" class="inline-flex items-center gap-1 text-sm text-ink-secondary hover:text-ink-brand">{{ __('catalog::taxonomy.categories.see_all') }}<x-ui::icon name="chevron-right" :size="16" data-flip /></a>
                    </div>
                </header>
                @if ($item['cards'] === [])
                    <p class="rounded-card bg-surface p-5 text-sm text-ink-muted">{{ __('catalog::taxonomy.categories.section_empty') }}</p>
                @else
                    <div class="grid grid-cols-[repeat(auto-fill,minmax(min(100%,300px),1fr))] gap-6" data-reveal-stagger>
                        @foreach ($item['cards'] as $card)
                            @include('catalog::vendor.categories._card', ['card' => $card])
                        @endforeach
                    </div>
                @endif
            </section>
        @endforeach

        @if ($categories->hasPages())
            <x-ui::pagination :paginator="$categories" />
        @endif
    @endif

    @include('catalog::vendor.products._modals', ['canManage' => (bool) $staff?->can('catalog.products.manage')])
</x-vendor::layout>
