@if (auth('admin')->user()?->can('catalog.attributes.manage'))
    <div class="flex items-center justify-end gap-1">
        <x-ui::icon-button icon="edit" size="sm" :label="__('catalog::common.edit_named', ['name' => $attribute->name])" :href="route('admin.catalog.attributes.edit', $attribute)" />
        <x-ui::row-actions>
            <x-ui::dropdown-item icon="edit" :href="route('admin.catalog.attributes.edit', $attribute)">{{ __('catalog::common.edit') }}</x-ui::dropdown-item>
            <x-ui::dropdown-divider />
            <x-ui::dropdown-item icon="trash" tone="danger" :data-confirm="__('catalog::attributes.delete_message')" :data-confirm-title="__('catalog::attributes.delete_title', ['name' => $attribute->name])" data-confirm-method="DELETE" :data-confirm-url="route('admin.catalog.attributes.destroy', $attribute)">{{ __('catalog::common.delete') }}</x-ui::dropdown-item>
        </x-ui::row-actions>
    </div>
@endif
