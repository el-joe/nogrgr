@if (auth('admin')->user()?->can('shipping.branches.manage'))
    <div class="flex items-center justify-end gap-1">
        <x-ui::icon-button icon="edit" size="sm" :label="__('inventory::branches.edit_named', ['name' => $branch->name])" :href="route('admin.shipping.branches.edit', $branch)" />
        <x-ui::row-actions>
            <x-ui::dropdown-item icon="edit" :href="route('admin.shipping.branches.edit', $branch)">{{ __('inventory::branches.edit') }}</x-ui::dropdown-item>
            <x-ui::dropdown-item icon="box" :href="route('admin.shipping.branches.edit', [$branch, 'tab' => 'stock'])">{{ __('inventory::branches.tabs.stock') }}</x-ui::dropdown-item>
            <x-ui::dropdown-divider />
            <x-ui::dropdown-item icon="trash" tone="danger" :data-confirm="__('inventory::branches.delete_message')" :data-confirm-title="__('inventory::branches.delete_title', ['name' => $branch->name])" data-confirm-method="DELETE" :data-confirm-url="route('admin.shipping.branches.destroy', $branch)">{{ __('inventory::branches.delete') }}</x-ui::dropdown-item>
        </x-ui::row-actions>
    </div>
@endif
