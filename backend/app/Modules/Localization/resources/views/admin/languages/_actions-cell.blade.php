@if (auth('admin')->user()?->can('localization.languages.manage'))
    <div class="flex items-center justify-end gap-1">
        <x-ui::icon-button icon="edit" size="sm" :label="__('localization::admin.edit_named', ['name' => $language->name])" :href="route('admin.localization.languages.edit', $language)" />
        @if ($language->is_active || ! ($language->isCore() || $language->is_default))
            <x-ui::row-actions>
                <x-ui::dropdown-item icon="edit" :href="route('admin.localization.languages.edit', $language)">{{ __('localization::admin.edit') }}</x-ui::dropdown-item>
                @if ($language->is_active)
                    {{-- ADM-091b: fill the central catalogue texts still missing in this language. --}}
                    <x-ui::dropdown-item icon="sparkles" data-ai-catalog-start :data-url="route('admin.localization.ai-translation.catalog')" :data-locale="$language->code" :data-ai-title="__('ai::admin_translation.actions.translate_catalog_title', ['language' => $language->name])" :data-ai-message="__('ai::admin_translation.actions.translate_catalog_message', ['language' => $language->name])" :data-ai-confirm="__('ai::admin_translation.actions.start')">{{ __('ai::admin_translation.actions.translate_catalog') }}</x-ui::dropdown-item>
                @endif
                @unless ($language->isCore() || $language->is_default)
                    <x-ui::dropdown-divider />
                    <x-ui::dropdown-item icon="trash" tone="danger" :data-confirm="__('localization::admin.languages.delete_message')" :data-confirm-title="__('localization::admin.languages.delete_title', ['name' => $language->name])" data-confirm-method="DELETE" :data-confirm-url="route('admin.localization.languages.destroy', $language)">{{ __('ui.delete') }}</x-ui::dropdown-item>
                @endunless
            </x-ui::row-actions>
        @endif
    </div>
@endif
