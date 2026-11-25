@if (auth('admin')->user()?->can('localization.languages.manage'))
    <div class="flex items-center justify-end gap-1">
        <x-ui::icon-button icon="edit" size="sm" :label="__('localization::admin.edit_named', ['name' => $language->name])" :href="route('admin.localization.languages.edit', $language)" />
        @unless ($language->isCore() || $language->is_default)
            <x-ui::row-actions>
                <x-ui::dropdown-item icon="edit" :href="route('admin.localization.languages.edit', $language)">{{ __('localization::admin.edit') }}</x-ui::dropdown-item>
                <x-ui::dropdown-divider />
                <x-ui::dropdown-item icon="trash" tone="danger" :data-confirm="__('localization::admin.languages.delete_message')" :data-confirm-title="__('localization::admin.languages.delete_title', ['name' => $language->name])" data-confirm-method="DELETE" :data-confirm-url="route('admin.localization.languages.destroy', $language)">{{ __('ui.delete') }}</x-ui::dropdown-item>
            </x-ui::row-actions>
        @endunless
    </div>
@endif
