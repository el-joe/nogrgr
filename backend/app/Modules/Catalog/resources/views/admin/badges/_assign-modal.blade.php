{{--
    Assign products modal (ADM-017f, Figma Admin Panel 65:29682): search by name / SKU
    (debounced, paged) with checkboxes, or a whole category (with its sub-categories).
    Rows are rendered by catalog.js from the JSON search endpoint.
--}}
<x-ui::modal id="badge-assign-modal" :title="__('catalog::badges.modal.title', ['name' => $badge->name])" size="lg" close-on-success>
    <form id="badge-assign-form" method="POST" action="{{ route('admin.catalog.badges.assign', $type) }}" data-ajax novalidate class="grid gap-5"
        data-badge-assign data-search-url="{{ route('admin.catalog.badges.search', $type) }}"
        data-label-submit="{{ __('catalog::badges.modal.submit', ['count' => ':count']) }}" data-label-submit-none="{{ __('catalog::badges.modal.submit_none') }}" data-label-submit-category="{{ __('catalog::badges.modal.submit_category') }}"
        data-label-select="{{ __('catalog::badges.modal.select', ['name' => ':name']) }}">
        @csrf
        <x-ui::input name="q" type="search" :label="__('catalog::badges.modal.search')" :placeholder="__('catalog::badges.modal.search_placeholder')" icon="search" autocomplete="off" data-badge-search data-unsaved-ignore />
        <x-ui::select name="category_id" :label="__('catalog::badges.modal.category')" :options="$categories" :placeholder="__('catalog::badges.modal.category_placeholder')" :helper="__('catalog::badges.modal.category_help')" allow-clear data-badge-category />

        <div class="overflow-hidden rounded-control border border-line" data-badge-results-card>
            <div class="flex items-center gap-3 border-b border-line bg-subtle px-4 py-2.5 text-xs font-medium text-ink-secondary">
                <input type="checkbox" class="ui-check" data-badge-select-all aria-label="{{ __('catalog::badges.modal.select_all') }}">
                <span class="flex-1">{{ __('catalog::badges.modal.product') }}</span>
                <span>{{ __('catalog::badges.modal.price') }}</span>
            </div>
            <ul data-badge-results class="max-h-80 divide-y divide-line overflow-y-auto" aria-live="polite"></ul>
            <div data-badge-state="loading" hidden class="grid gap-3 p-4" aria-busy="true">
                <x-ui::skeleton shape="rect" class="h-10 w-full" />
                <x-ui::skeleton shape="rect" class="h-10 w-full" />
                <x-ui::skeleton shape="rect" class="h-10 w-2/3" />
            </div>
            <p data-badge-state="start" class="px-4 py-8 text-center text-sm text-ink-secondary">{{ __('catalog::badges.modal.start') }}</p>
            <div data-badge-state="empty" hidden>
                <x-ui::empty-state compact variant="no-results" :description="__('catalog::badges.modal.no_results')" />
            </div>
            <div data-badge-state="error" hidden class="grid justify-items-center gap-2 pb-4">
                <x-ui::empty-state compact variant="error" :description="__('catalog::badges.modal.error')" />
                <x-ui::button variant="secondary" size="sm" icon="refresh" data-badge-retry>{{ __('catalog::common.retry') }}</x-ui::button>
            </div>
            <div data-badge-more hidden class="border-t border-line p-3 text-center">
                <x-ui::button variant="ghost" size="sm" data-badge-load-more>{{ __('catalog::badges.modal.load_more') }}</x-ui::button>
            </div>
        </div>
        <template data-badge-row>
            <li class="has-checked:bg-primary-subtle">
                <label class="flex cursor-pointer items-center gap-3 px-4 py-2.5">
                    <input type="checkbox" class="ui-check" name="product_ids[]" value="">
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-medium text-ink" data-field="name"></span>
                        <span class="block truncate text-xs text-ink-secondary" dir="ltr" data-field="sku"></span>
                    </span>
                    <span class="text-sm font-medium text-ink" dir="ltr" data-field="price"></span>
                </label>
            </li>
        </template>
    </form>
    <x-slot:footer>
        <x-ui::button variant="secondary" data-overlay-close>{{ __('catalog::common.cancel') }}</x-ui::button>
        <x-ui::button type="submit" form="badge-assign-form" icon="plus" disabled data-badge-submit>{{ __('catalog::badges.modal.submit_none') }}</x-ui::button>
    </x-slot:footer>
</x-ui::modal>
