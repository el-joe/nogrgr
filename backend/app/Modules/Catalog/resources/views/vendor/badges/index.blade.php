{{--
    Vendor › Products › Badges (VEN-018 / 018a / 018b, Vendor Panel (new screens) 83:103600):
    Add category / Add products, one tab per active platform badge with its product count,
    then "<badge> — your order": the platform's products on the badge (read-only, "Admin")
    above the store's own picks (drag to sort, remove). Saved with Save order.
    Data: $badge (ProductBadge|null), $tabs, $adminRows, $storeRows, $categoryOptions.
--}}
@php
    $staff = auth('vendor')->user();
    $canManage = (bool) $staff?->can('catalog.badges.manage');
    $tabItems = [];
    foreach ($tabs as $tab) {
        $tabItems[(string) $tab['badge']->id] = ['label' => $tab['badge']->name, 'count' => $tab['count'], 'href' => vendor_route('catalog.badges.show', ['badge' => $tab['badge']->id])];
    }
    $badgeName = $badge?->name ?? '';
@endphp
<x-vendor::layout :title="__('catalog::taxonomy.badges.title')" page="vendor-catalog">
    <x-ui::page-header
        :title="__('catalog::taxonomy.badges.title')"
        :subtitle="__('catalog::taxonomy.badges.subtitle')"
        :breadcrumbs="[__('catalog::vendor.products.title') => vendor_route('catalog.products.index'), __('catalog::taxonomy.badges.title') => null]"
    >
        @if ($canManage && $badge !== null)
            <x-slot:actions>
                @if ($storeRows !== [])
                    <x-ui::button variant="secondary" icon="check" data-sort-save>{{ __('catalog::vendor.sort.save') }}</x-ui::button>
                @endif
                <x-ui::button variant="secondary" icon="layers" data-overlay-open="badge-category-modal">{{ __('catalog::taxonomy.badges.add_category') }}</x-ui::button>
                <x-ui::button icon="plus" data-overlay-open="badge-products-modal">{{ __('catalog::taxonomy.badges.add_products') }}</x-ui::button>
            </x-slot:actions>
        @endif
        @if ($tabItems !== [])
            <x-slot:tabs>
                <x-ui::tabs id="badge-tabs" :active="(string) $badge?->id" :label="__('catalog::taxonomy.badges.title')" :items="$tabItems" />
            </x-slot:tabs>
        @endif
    </x-ui::page-header>

    @if ($badge === null)
        <x-ui::card>
            <x-ui::empty-state icon="star" :title="__('catalog::taxonomy.badges.no_badges_title')" :description="__('catalog::taxonomy.badges.no_badges_description')" />
        </x-ui::card>
    @elseif ($adminRows === [] && $storeRows === [])
        <x-ui::card>
            <x-ui::empty-state icon="star" :title="__('catalog::taxonomy.badges.empty_title')" :description="__('catalog::taxonomy.badges.empty_description')">
                @if ($canManage)
                    <x-ui::button icon="plus" data-overlay-open="badge-products-modal">{{ __('catalog::taxonomy.badges.add_products') }}</x-ui::button>
                @endif
            </x-ui::empty-state>
        </x-ui::card>
    @else
        <section class="grid gap-3 rounded-card border border-line bg-surface p-4 shadow-card">
            <div class="grid gap-0.5">
                <h2 class="text-base font-semibold text-ink">{{ __('catalog::taxonomy.badges.list_title', ['badge' => $badgeName]) }}</h2>
                <p class="text-xs text-ink-secondary">{{ __('catalog::taxonomy.badges.list_hint') }}</p>
            </div>
            @if ($adminRows !== [])
                <ol class="grid gap-2" aria-label="{{ __('catalog::taxonomy.badges.admin_row') }}">
                    @foreach ($adminRows as $index => $row)
                        @php($view = $row['view'])
                        <li class="flex items-center gap-3 rounded-control border border-line bg-subtle px-3 py-2">
                            <x-ui::icon name="shield" :size="18" class="text-ink-muted" />
                            <span class="w-6 text-xs text-ink-muted" dir="ltr">{{ $index + 1 }}</span>
                            @if ($view->primaryImageUrl())
                                <img src="{{ $view->primaryImageUrl() }}" alt="" class="size-9 rounded-chip object-cover" loading="lazy">
                            @else
                                <span class="grid size-9 place-items-center rounded-chip bg-primary-subtle text-ink-brand"><x-ui::icon name="box" :size="16" /></span>
                            @endif
                            <span class="grid min-w-0 flex-1">
                                <span class="truncate text-sm font-medium text-ink">{{ $view->name }}</span>
                                <span class="text-xs text-ink-secondary">{{ __('catalog::taxonomy.badges.admin_row') }}</span>
                            </span>
                            <x-ui::badge tone="neutral" dot>{{ __('catalog::taxonomy.badges.admin') }}</x-ui::badge>
                            @if ($canManage)
                                <x-ui::icon-button icon="trash" size="sm" :label="__('catalog::taxonomy.badges.remove')" :data-confirm="__('catalog::taxonomy.badges.remove_text')" :data-confirm-title="__('catalog::taxonomy.badges.remove_title', ['name' => $view->name, 'badge' => $badgeName])" data-confirm-method="DELETE" :data-confirm-url="vendor_route('catalog.badges.products.remove', ['badge' => $badge->id, 'product' => $row['id']])" />
                            @endif
                        </li>
                    @endforeach
                </ol>
            @endif
            @if ($storeRows !== [])
                @include('catalog::vendor.categories._sort-list', [
                    'rows' => $storeRows,
                    'sortUrl' => vendor_route('catalog.badges.sort', ['badge' => $badge->id]),
                    'canManage' => $canManage,
                    'title' => __('catalog::taxonomy.badges.store_row'),
                    'note' => __('catalog::taxonomy.badges.store_row'),
                    'removeUrl' => fn (int $id): string => vendor_route('catalog.badges.products.remove', ['badge' => $badge->id, 'product' => $id]),
                    'removeLabel' => __('catalog::taxonomy.badges.remove'),
                    'removeText' => __('catalog::taxonomy.badges.remove_text'),
                    'removeTitle' => fn (string $name): string => __('catalog::taxonomy.badges.remove_title', ['name' => $name, 'badge' => $badgeName]),
                ])
            @endif
        </section>
    @endif

    @if ($canManage && $badge !== null)
        <x-ui::modal id="badge-products-modal" :title="__('catalog::taxonomy.badges.add_products_title', ['badge' => $badgeName])">
            <form id="badge-products-form" method="POST" action="{{ vendor_route('catalog.badges.products.add', ['badge' => $badge->id]) }}" class="grid gap-4" data-ajax novalidate>
                @csrf
                <x-ui::select name="product_ids" multiple :label="__('catalog::taxonomy.products.picker')" :placeholder="__('catalog::taxonomy.products.picker_placeholder')" :ajax-url="vendor_route('catalog.badges.search')" required />
            </form>
            <x-slot:footer>
                <x-ui::button variant="secondary" data-overlay-close>{{ __('catalog::taxonomy.products.cancel') }}</x-ui::button>
                <x-ui::button type="submit" form="badge-products-form" icon="plus">{{ __('catalog::taxonomy.products.save') }}</x-ui::button>
            </x-slot:footer>
        </x-ui::modal>

        <x-ui::modal id="badge-category-modal" :title="__('catalog::taxonomy.badges.add_category_title', ['badge' => $badgeName])">
            <form id="badge-category-form" method="POST" action="{{ vendor_route('catalog.badges.add-category', ['badge' => $badge->id]) }}" class="grid gap-4" data-ajax novalidate>
                @csrf
                <x-ui::select name="category" :label="__('catalog::taxonomy.fields.category')" :options="$categoryOptions" :placeholder="__('catalog::taxonomy.fields.category')" :helper="__('catalog::taxonomy.badges.category_hint')" required />
            </form>
            <x-slot:footer>
                <x-ui::button variant="secondary" data-overlay-close>{{ __('catalog::taxonomy.products.cancel') }}</x-ui::button>
                <x-ui::button type="submit" form="badge-category-form" icon="plus">{{ __('catalog::taxonomy.products.save') }}</x-ui::button>
            </x-slot:footer>
        </x-ui::modal>
    @endif
</x-vendor::layout>
