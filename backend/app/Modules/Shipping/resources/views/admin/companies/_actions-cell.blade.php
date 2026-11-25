<div class="flex items-center justify-end gap-1">
    <x-ui::icon-button icon="eye" size="sm" :label="__('shipping::companies.view_named', ['name' => $company->name])" :href="route('admin.shipping.companies.show', $company)" />
    @if (auth('admin')->user()?->can('shipping.companies.manage'))
        <x-ui::icon-button icon="edit" size="sm" :label="__('shipping::companies.edit_named', ['name' => $company->name])" :href="route('admin.shipping.companies.edit', $company)" />
        <x-ui::row-actions>
            <x-ui::dropdown-item icon="calendar" :href="route('admin.shipping.companies.edit', [$company, 'tab' => 'day-rules'])">{{ __('shipping::companies.tabs.day_rules') }}</x-ui::dropdown-item>
            <x-ui::dropdown-item icon="clock" :href="route('admin.shipping.companies.edit', [$company, 'tab' => 'stop-periods'])">{{ __('shipping::companies.tabs.stop_periods') }}</x-ui::dropdown-item>
            <x-ui::dropdown-divider />
            <x-ui::dropdown-item icon="trash" tone="danger" :data-confirm="__('shipping::companies.delete_message')" :data-confirm-title="__('shipping::companies.delete_title', ['name' => $company->name])" data-confirm-method="DELETE" :data-confirm-url="route('admin.shipping.companies.destroy', $company)">{{ __('shipping::common.delete') }}</x-ui::dropdown-item>
        </x-ui::row-actions>
    @endif
</div>
