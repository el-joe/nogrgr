{{--
    Category drill-down (#115, old controller form): one Select2 per level built from the
    [data-category-tree] JSON island; choosing a deepest-level category adds it as a tag
    (hidden category_ids[]). Existing categories are rendered as tags.
--}}
<x-ui::field :label="__('catalog::products.fields.category_ids')" :helper="__('catalog::products.sections.categories_hint')">
    @if ($categoryTree === [])
        <p class="text-[13px] text-ink-secondary">{{ __('catalog::products.categories.none') }}
            @can('catalog.categories.manage')
                <a href="{{ route('admin.catalog.categories.create') }}" class="font-medium text-ink-brand hover:underline">{{ __('catalog::products.categories.add_category') }}</a>
            @endcan
        </p>
    @else
        <div class="grid gap-3" data-category-picker data-level-label="{{ __('catalog::products.fields.category_level', ['level' => ':level']) }}" data-placeholder="{{ __('catalog::products.fields.category_pick') }}" data-remove-label="{{ __('catalog::products.categories.remove', ['name' => ':name']) }}">
            <ul class="flex flex-wrap gap-2" data-category-tags aria-label="{{ __('catalog::products.fields.category_ids') }}">
                @foreach ($selectedCategories as $category)
                    <li data-category-tag data-id="{{ $category['id'] }}" class="inline-flex items-center gap-1.5 rounded-chip bg-primary-subtle py-1 ps-2.5 pe-1 text-[13px] font-medium text-ink-brand">
                        <span>{{ $category['path'] }}</span>
                        <button type="button" data-category-remove class="grid size-6 place-items-center rounded-chip hover:bg-surface/70" aria-label="{{ __('catalog::products.categories.remove', ['name' => $category['path']]) }}"><x-ui::icon name="x" :size="14" /></button>
                        <input type="hidden" name="category_ids[]" value="{{ $category['id'] }}">
                    </li>
                @endforeach
            </ul>
            <p data-category-empty class="text-[13px] text-ink-secondary" @if ($selectedCategories !== []) hidden @endif>{{ __('catalog::products.categories.empty') }}</p>
            <div class="grid gap-3 sm:grid-cols-2" data-category-levels></div>
            <p data-error-for="category_ids" class="ui-error text-xs text-danger" role="alert" hidden></p>
        </div>
        <template data-category-tag-template>
            <li data-category-tag class="inline-flex items-center gap-1.5 rounded-chip bg-primary-subtle py-1 ps-2.5 pe-1 text-[13px] font-medium text-ink-brand">
                <span data-category-path></span>
                <button type="button" data-category-remove class="grid size-6 place-items-center rounded-chip hover:bg-surface/70"><x-ui::icon name="x" :size="14" /></button>
                <input type="hidden" name="category_ids[]">
            </li>
        </template>
    @endif
</x-ui::field>
