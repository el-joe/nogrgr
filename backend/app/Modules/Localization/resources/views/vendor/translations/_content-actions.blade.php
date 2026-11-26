{{-- Content field actions: edit (modal with "Translate"), unlock a hand translation. --}}
@if ($canManage)
    <div class="flex items-center justify-end gap-1">
        <x-ui::icon-button icon="edit" size="sm" :label="__('localization::vendor_translations.edit_field', ['id' => $id])"
            data-translation-edit
            :data-type="$type->value"
            :data-id="$id"
            :data-field="$field"
            :data-label="'#'.$id.' · '.__('localization::vendor_translations.fields_names.'.$field)"
            :data-english="$english ?? ''"
            :data-value="$value ?? ''"
            :data-long="$type->isLong($field) ? '1' : '0'"
            :data-url="vendor_route('settings.translations.content.update')"
            :data-suggest-url="vendor_route('settings.translations.content.suggest')" />
        @if ($locked)
            <x-ui::icon-button icon="lock" size="sm" :label="__('localization::vendor_translations.unlock')"
                :href="vendor_route('settings.translations.content.unlock', ['type' => $type->value, 'id' => $id, 'field' => $field, 'locale' => $locale])"
                data-confirm-method="POST" data-confirm-tone="brand" :data-confirm-title="__('localization::vendor_translations.unlock')" :data-confirm="__('localization::vendor_translations.unlock_confirm')" />
        @endif
    </div>
@endif
