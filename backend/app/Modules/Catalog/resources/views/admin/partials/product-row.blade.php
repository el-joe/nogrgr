{{--
    Sortable product row (category product order, badge lists): handle, position,
    thumbnail, name + subtitle, and a menu with move to top / bottom (and remove).
--}}
<li data-sortable-item data-id="{{ $product->id }}" class="flex items-center gap-3 rounded-control border border-line bg-surface px-3 py-2.5 transition-colors focus-within:border-primary hover:bg-subtle data-dragging:border-primary">
    <button type="button" data-drag-handle class="grid size-7 shrink-0 cursor-grab place-items-center rounded-chip text-ink-muted hover:bg-surface hover:text-ink focus-visible:outline-2 focus-visible:outline-primary" aria-label="{{ __('catalog::common.drag_handle', ['name' => $product->name]) }}" title="{{ __('catalog::common.drag_hint') }}">
        <x-ui::icon name="menu" :size="16" />
    </button>
    <span data-position class="w-6 shrink-0 text-center text-xs text-ink-secondary" dir="ltr">{{ $loop->iteration }}</span>
    @include('catalog::admin.partials.thumb', ['url' => $product->primaryImageUrl()])
    <span class="min-w-0 flex-1">
        <span class="block truncate text-sm font-medium text-ink">{{ $product->name }}</span>
        <span class="block truncate text-xs text-ink-secondary">{{ $subtitle }}</span>
    </span>
    <x-ui::row-actions>
        <x-ui::dropdown-item icon="arrow-up" data-sort-move="top">{{ __('catalog::common.move_top') }}</x-ui::dropdown-item>
        <x-ui::dropdown-item icon="arrow-down" data-sort-move="bottom">{{ __('catalog::common.move_bottom') }}</x-ui::dropdown-item>
        @if ($remove)
            <x-ui::dropdown-divider />
            <x-ui::dropdown-item icon="trash" tone="danger" :data-confirm="__('catalog::badges.remove_message')" :data-confirm-title="__('catalog::badges.remove_title', ['name' => $product->name])" :data-confirm-label="__('catalog::badges.remove')" data-confirm-method="DELETE" :data-confirm-url="$remove">{{ __('catalog::badges.remove') }}</x-ui::dropdown-item>
        @endif
    </x-ui::row-actions>
</li>
