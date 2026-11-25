@if ($canLock)
    <x-ui::switch
        :name="'lock-'.md5($file->id.'|'.$row['key'])"
        :checked="$row['locked']"
        :url="route('admin.localization.key-locks.update', ['file' => $file->id, 'key' => $row['key']])"
        field="locked"
        :aria-label="__('localization::translations.actions.lock_label', ['key' => $row['key']])"
    />
@elseif ($row['locked'])
    <x-ui::badge tone="danger">{{ __('localization::translations.locked_badge') }}</x-ui::badge>
@else
    <span class="text-ink-muted">—</span>
@endif
