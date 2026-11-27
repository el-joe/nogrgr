{{--
    Admin › Catalog › Products › Sort (ADM-012, Figma Admin Panel 65:21308): products in
    storefront order, 20 per page with "Load more", filtered by category /
    search; drag rows (or arrow keys on a handle), Save order keeps the positions the
    loaded rows held; Reset to newest orders everything newest first.
--}}
<x-layouts.panel surface="admin" :title="__('catalog::products.sort.title')" data-page="admin-products">
    <div class="grid gap-6" data-product-sort data-sort-data="{{ route('admin.catalog.products.sort.data') }}" data-sort-save-url="{{ route('admin.catalog.products.sort.save') }}" data-count-template="{{ __('catalog::products.sort.order_count', ['from' => ':from', 'to' => ':to', 'total' => ':total']) }}">
        <x-ui::page-header
            :title="__('catalog::products.sort.title')"
            :subtitle="__('catalog::products.sort.subtitle')"
            :breadcrumbs="[__('navigation.sections.catalog') => null, __('catalog::products.title') => route('admin.catalog.products.index'), __('catalog::products.sort.title') => null]"
        >
            <x-slot:actions>
                <x-ui::button variant="secondary" icon="refresh" :data-confirm="__('catalog::products.sort.reset_message')" :data-confirm-title="__('catalog::products.sort.reset_title')" data-confirm-tone="primary" data-confirm-method="POST" :data-confirm-url="route('admin.catalog.products.sort.reset')">{{ __('catalog::products.sort.reset') }}</x-ui::button>
                <x-ui::button icon="check" data-sort-save>{{ __('catalog::products.sort.save') }}</x-ui::button>
            </x-slot:actions>
        </x-ui::page-header>

        <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
            <x-ui::card>
                <div class="mb-4 flex items-center justify-between gap-3">
                    <h2 class="text-base font-semibold text-ink" data-sort-count>{{ __('catalog::products.sort.order') }}</h2>
                    <div data-sort-more hidden>
                        <x-ui::button variant="ghost" size="sm">{{ __('catalog::products.sort.load_more') }}</x-ui::button>
                    </div>
                </div>
                <div data-sort-state="loading" class="grid gap-2" aria-busy="true">
                    @for ($i = 0; $i < 5; $i++)
                        <x-ui::skeleton shape="rect" class="h-14 w-full" />
                    @endfor
                </div>
                <div data-sort-state="empty" hidden>
                    <x-ui::empty-state compact variant="no-results" :description="__('catalog::products.sort.empty')" />
                </div>
                <div data-sort-state="error" hidden class="grid justify-items-center gap-2">
                    <x-ui::empty-state compact variant="error" :description="__('catalog::products.sort.error')" />
                    <x-ui::button variant="secondary" size="sm" icon="refresh" data-sort-retry>{{ __('catalog::common.retry') }}</x-ui::button>
                </div>
                <ol data-sort-list class="grid gap-2"></ol>
                <p data-sort-dirty hidden class="mt-4 text-[13px] text-warning" role="status">{{ __('catalog::products.sort.unsaved') }}</p>
                <template data-sort-row>
                    <li data-sort-item class="flex items-center gap-3 rounded-control border border-line bg-surface px-3 py-2.5 transition-colors focus-within:border-primary hover:bg-subtle">
                        <button type="button" data-drag-handle class="grid size-7 shrink-0 cursor-grab place-items-center rounded-chip text-ink-muted hover:bg-subtle hover:text-ink focus-visible:outline-2 focus-visible:outline-primary" title="{{ __('catalog::common.drag_hint') }}">
                            <x-ui::icon name="menu" :size="16" />
                        </button>
                        <span data-position class="w-6 shrink-0 text-center text-xs text-ink-secondary" dir="ltr"></span>
                        <span data-field="image" class="size-10 shrink-0 overflow-hidden rounded-control bg-linear-to-br from-primary-subtle to-primary/40" aria-hidden="true"></span>
                        <span class="min-w-0 flex-1">
                            <span data-field="name" class="block truncate text-sm font-medium text-ink"></span>
                            <span data-field="subtitle" class="block truncate text-xs text-ink-secondary"></span>
                        </span>
                        <span data-field="status" class="inline-flex h-5 items-center rounded-full bg-success-bg px-2 text-xs font-medium text-success data-[status=out_of_stock]:bg-danger-bg data-[status=out_of_stock]:text-danger data-[status=draft]:bg-subtle data-[status=draft]:text-ink-secondary data-[status=archived]:bg-subtle data-[status=archived]:text-ink-secondary"></span>
                    </li>
                </template>
            </x-ui::card>

            <x-ui::card :title="__('catalog::products.sort.filter')">
                <div class="grid gap-4">
                    <x-ui::select name="category_id" :label="__('catalog::products.filters.category')" :options="$categoryOptions" :placeholder="__('catalog::products.sort.all_categories')" allow-clear data-sort-filter />
                    <x-ui::input name="search" type="search" :label="__('catalog::products.sort.search')" :placeholder="__('catalog::products.sort.search_placeholder')" data-sort-filter />
                    <p class="flex gap-2 rounded-control bg-info-bg p-3 text-xs text-info"><x-ui::icon name="info" :size="16" class="shrink-0" />{{ __('catalog::products.sort.tip') }}</p>
                </div>
            </x-ui::card>
        </div>
    </div>
</x-layouts.panel>
