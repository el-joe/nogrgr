{{--
    One level of the category tree (recursive). Every <li> keeps an (empty) child list so
    rows can be dropped into it. $editable adds drag handles; $details adds the row
    actions and the detail-panel selection used on the index page.
--}}
@php
    $canManage = (bool) auth('admin')->user()?->can('catalog.categories.manage');
@endphp
<ul @class(['grid', 'ms-6 border-s border-line ps-2' => $depth > 1]) data-category-list role="{{ $depth > 1 ? 'group' : 'tree' }}" @if ($depth === 1) aria-label="{{ __('catalog::categories.tree') }}" @endif>
    @foreach ($nodes as $node)
        @php
            $category = $node['category'];
            $hasChildren = $node['children'] !== [];
        @endphp
        <li data-category-id="{{ $category->id }}" role="treeitem" aria-level="{{ $depth }}" @if ($hasChildren) aria-expanded="true" @endif data-category-node class="min-w-0">
            <div @class(['group flex min-h-12 items-center gap-2 border-b border-line px-1 py-1.5 transition-colors hover:bg-subtle', 'cursor-pointer' => $details]) @if ($details) data-category-select="{{ $category->id }}" @endif>
                @if ($editable && $canManage)
                    <button type="button" data-drag-handle class="grid size-7 shrink-0 cursor-grab place-items-center rounded-chip text-ink-muted hover:bg-surface hover:text-ink focus-visible:outline-2 focus-visible:outline-primary" aria-label="{{ __('catalog::common.drag_handle', ['name' => $category->name]) }}" title="{{ __('catalog::common.drag_hint') }}">
                        <x-ui::icon name="menu" :size="16" />
                    </button>
                @endif
                @if ($hasChildren)
                    <button type="button" data-category-toggle class="grid size-6 shrink-0 place-items-center rounded-chip text-ink-secondary hover:bg-surface" aria-label="{{ __('catalog::categories.expand', ['name' => $category->name]) }}" aria-expanded="true">
                        <x-ui::icon name="chevron-down" :size="16" class="transition-transform" data-toggle-icon />
                    </button>
                @else
                    <span class="grid size-6 shrink-0 place-items-center text-ink-muted" aria-hidden="true"><x-ui::icon name="minus" :size="14" /></span>
                @endif
                @include('catalog::admin.partials.thumb', ['url' => $category->thumbnailUrl(), 'webp' => $category->thumbnailWebpUrl(), 'avif' => $category->thumbnailAvifUrl(), 'size' => 'size-8'])
                <span @class(['min-w-0 flex-1 truncate text-sm text-ink', 'font-medium' => $depth === 1])>{{ $category->name }}</span>
                <span class="hidden shrink-0 text-xs text-ink-secondary sm:inline" dir="auto">{{ trans_choice('catalog::common.products_count', (int) $category->products_count, ['count' => number_format((int) $category->products_count)]) }}</span>
                @if ($category->status === \App\Modules\Catalog\Enums\RecordStatus::Inactive)
                    <x-ui::badge tone="neutral">{{ __('catalog::categories.hidden') }}</x-ui::badge>
                @elseif ($category->is_featured)
                    <x-ui::badge tone="brand">{{ __('catalog::categories.featured') }}</x-ui::badge>
                @endif
                @if ($details && $canManage)
                    <x-ui::icon-button icon="edit" size="sm" :label="__('catalog::common.edit_named', ['name' => $category->name])" :href="route('admin.catalog.categories.edit', $category)" />
                    <x-ui::row-actions>
                        <x-ui::dropdown-item icon="edit" :href="route('admin.catalog.categories.edit', $category)">{{ __('catalog::common.edit') }}</x-ui::dropdown-item>
                        <x-ui::dropdown-item icon="sort" :href="route('admin.catalog.categories.products', $category)">{{ __('catalog::categories.products_order') }}</x-ui::dropdown-item>
                        <x-ui::dropdown-divider />
                        <x-ui::dropdown-item icon="trash" tone="danger" :data-confirm="__('catalog::categories.delete_message')" :data-confirm-title="__('catalog::categories.delete_title', ['name' => $category->name])" data-confirm-method="DELETE" :data-confirm-url="route('admin.catalog.categories.destroy', $category)">{{ __('catalog::common.delete') }}</x-ui::dropdown-item>
                    </x-ui::row-actions>
                @endif
            </div>
            @include('catalog::admin.categories._nodes', ['nodes' => $node['children'], 'depth' => $depth + 1, 'editable' => $editable, 'details' => $details])
        </li>
    @endforeach
</ul>
