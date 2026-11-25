@php
    $params = ['file' => $file->id, 'key' => $row['key']];
    $overridden = $row['status'] === \App\Modules\Localization\Enums\TranslationKeyStatus::Overridden;
@endphp
<div class="flex items-center justify-end gap-1">
    <x-ui::icon-button icon="eye" size="sm" :label="__('localization::translations.actions.view', ['key' => $row['key']])" data-remote-modal="translation-view-modal" :data-url="route('admin.localization.translations.show', $params)" />
    @if ($canManage && ! $row['locked'])
        <x-ui::icon-button icon="edit" size="sm" :label="__('localization::translations.actions.edit', ['key' => $row['key']])" data-remote-modal="translation-modal" :data-url="route('admin.localization.translations.edit', $params)" />
    @endif
    @if (($canManage && $overridden && ! $row['locked']) || $canLock)
        <x-ui::row-actions :label="__('localization::translations.actions.more', ['key' => $row['key']])">
            @if ($canLock)
                <x-ui::dropdown-item :icon="$row['locked'] ? 'key' : 'shield'"
                    :data-confirm="$row['locked'] ? __('localization::translations.actions.unlock') : __('localization::translations.actions.lock')"
                    :data-confirm-title="$row['key']"
                    data-confirm-tone="primary"
                    data-confirm-method="PATCH"
                    :data-confirm-url="route('admin.localization.key-locks.update', $params + ['locked' => $row['locked'] ? 0 : 1])">{{ $row['locked'] ? __('localization::translations.actions.unlock') : __('localization::translations.actions.lock') }}</x-ui::dropdown-item>
            @endif
            @if ($canManage && $overridden && ! $row['locked'])
                <x-ui::dropdown-divider />
                <x-ui::dropdown-item icon="refresh" tone="danger" :data-confirm="__('localization::translations.revert_message')" :data-confirm-title="__('localization::translations.revert_title', ['key' => $row['key']])" data-confirm-method="DELETE" :data-confirm-url="route('admin.localization.translations.revert', $params)">{{ __('localization::translations.actions.revert') }}</x-ui::dropdown-item>
            @endif
        </x-ui::row-actions>
    @endif
</div>
