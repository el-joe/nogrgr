@if (auth('admin')->user()?->can('localization.currencies.manage'))
    <div class="flex items-center justify-end gap-1">
        <x-ui::icon-button icon="edit" size="sm" :label="__('localization::admin.edit_named', ['name' => $currency->name])" :href="route('admin.localization.currencies.edit', $currency)" />
        @unless ($currency->isBase())
            <x-ui::row-actions>
                <x-ui::dropdown-item icon="edit" :href="route('admin.localization.currencies.edit', $currency)">{{ __('localization::admin.edit') }}</x-ui::dropdown-item>
                <x-ui::dropdown-divider />
                <x-ui::dropdown-item icon="trash" tone="danger" :data-confirm="__('localization::admin.currencies.delete_message')" :data-confirm-title="__('localization::admin.currencies.delete_title', ['name' => $currency->name])" data-confirm-method="DELETE" :data-confirm-url="route('admin.localization.currencies.destroy', $currency)">{{ __('ui.delete') }}</x-ui::dropdown-item>
            </x-ui::row-actions>
        @endunless
    </div>
@endif
