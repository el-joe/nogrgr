@if (auth('admin')->user()?->can('shipping.fixed-costs.manage'))
    <div class="flex items-center justify-end gap-1">
        <x-ui::icon-button icon="edit" size="sm" :label="__('shipping::fixed_costs.edit_named', ['country' => $cost->country->name])" data-remote-modal="fixed-cost-modal" :data-url="route('admin.shipping.fixed-costs.edit', $cost)" />
        <x-ui::row-actions>
            <x-ui::dropdown-item icon="edit" data-remote-modal="fixed-cost-modal" :data-url="route('admin.shipping.fixed-costs.edit', $cost)">{{ __('shipping::common.edit') }}</x-ui::dropdown-item>
            <x-ui::dropdown-divider />
            <x-ui::dropdown-item icon="trash" tone="danger" :data-confirm="__('shipping::fixed_costs.delete_message')" :data-confirm-title="__('shipping::fixed_costs.delete_title', ['country' => $cost->country->name])" data-confirm-method="DELETE" :data-confirm-url="route('admin.shipping.fixed-costs.destroy', $cost)">{{ __('shipping::common.delete') }}</x-ui::dropdown-item>
        </x-ui::row-actions>
    </div>
@endif
