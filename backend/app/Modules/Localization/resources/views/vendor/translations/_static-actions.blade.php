{{-- Static text actions: edit (modal with "Translate"), reset to the platform text. --}}
@if ($canManage && ! $row['platform_locked'])
    <div class="flex items-center justify-end gap-1">
        <x-ui::icon-button icon="edit" size="sm" :label="__('localization::vendor_translations.edit_key', ['key' => $row['key']])"
            data-translation-edit
            :data-key="$row['key']"
            :data-label="$row['key']"
            :data-english="$row['english'] ?? ''"
            :data-value="$row['value'] ?? ''"
            :data-url="vendor_route('settings.translations.static.update')"
            :data-suggest-url="vendor_route('settings.translations.static.suggest')" />
        @if ($row['value'] !== null)
            <x-ui::icon-button icon="return" size="sm" :label="__('localization::vendor_translations.reset', ['key' => $row['key']])"
                :href="vendor_route('settings.translations.static.reset', ['key' => $row['key'], 'locale' => $locale])"
                data-confirm-method="POST" :data-confirm-title="__('localization::vendor_translations.reset_title')" :data-confirm="__('localization::vendor_translations.reset_confirm')" />
        @endif
    </div>
@elseif ($row['platform_locked'])
    <span class="sr-only">{{ __('localization::vendor_translations.lock.platform') }}</span>
@endif
