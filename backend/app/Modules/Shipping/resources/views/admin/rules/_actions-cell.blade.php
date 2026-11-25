@if (auth('admin')->user()?->can('shipping.rules.manage'))
    <div class="flex items-center justify-end gap-1">
        <x-ui::icon-button icon="edit" size="sm" :label="__('shipping::rules.edit_named', ['name' => $rule->name])" :href="route('admin.shipping.rules.edit', $rule)" />
        <x-ui::row-actions>
            <x-ui::dropdown-item icon="edit" :href="route('admin.shipping.rules.edit', $rule)">{{ __('shipping::common.edit') }}</x-ui::dropdown-item>
            <x-ui::dropdown-divider />
            <x-ui::dropdown-item icon="trash" tone="danger" :data-confirm="__('shipping::rules.delete_message')" :data-confirm-title="__('shipping::rules.delete_title', ['name' => $rule->name])" data-confirm-method="DELETE" :data-confirm-url="route('admin.shipping.rules.destroy', $rule)">{{ __('shipping::common.delete') }}</x-ui::dropdown-item>
        </x-ui::row-actions>
    </div>
@endif
