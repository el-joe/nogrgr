@php
    $canManage = (bool) auth('admin')->user()?->can('catalog.products.manage');
@endphp
<div class="flex items-center justify-end gap-1">
    @if ($product->trashed())
        @if ($canManage)
            <x-ui::button size="sm" variant="secondary" icon="refresh" :data-confirm="__('catalog::products.restore')" :data-confirm-title="__('catalog::products.restore').' '.$product->name" data-confirm-tone="primary" data-confirm-method="PATCH" :data-confirm-url="route('admin.catalog.products.restore', $product->id)">{{ __('catalog::products.restore') }}</x-ui::button>
        @endif
    @else
        <x-ui::icon-button icon="eye" size="sm" :label="__('catalog::products.price_list')" data-remote-modal="price-list-modal" :data-url="route('admin.catalog.products.prices', $product)" />
        @if ($canManage)
            <x-ui::icon-button icon="edit" size="sm" :label="__('catalog::products.edit_named', ['name' => $product->name])" :href="route('admin.catalog.products.edit', $product)" />
            <x-ui::row-actions>
                <x-ui::dropdown-item icon="edit" :href="route('admin.catalog.products.edit', $product)">{{ __('catalog::products.edit') }}</x-ui::dropdown-item>
                <x-ui::dropdown-item icon="eye" data-remote-modal="price-list-modal" :data-url="route('admin.catalog.products.prices', $product)">{{ __('catalog::products.price_list') }}</x-ui::dropdown-item>
                <x-ui::dropdown-divider />
                <x-ui::dropdown-item icon="trash" tone="danger" :data-confirm="__('catalog::products.delete_message')" :data-confirm-title="__('catalog::products.delete_title', ['name' => $product->name])" data-confirm-method="DELETE" :data-confirm-url="route('admin.catalog.products.destroy', $product)">{{ __('catalog::products.delete') }}</x-ui::dropdown-item>
            </x-ui::row-actions>
        @endif
    @endif
</div>
