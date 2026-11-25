<div class="flex items-center justify-end gap-1">
    <x-ui::row-actions>
        <x-ui::dropdown-item icon="eye" :href="route('admin.ui-kit')">{{ __('platform::ui-kit.actions.view') }}</x-ui::dropdown-item>
        <x-ui::dropdown-item icon="copy" data-ui-kit-toast="info">{{ __('platform::ui-kit.actions.duplicate') }}</x-ui::dropdown-item>
        <x-ui::dropdown-divider />
        <x-ui::dropdown-item icon="trash" tone="danger" :data-confirm="__('platform::ui-kit.confirm_delete_message')" :data-confirm-title="__('platform::ui-kit.confirm_delete_title', ['name' => $row['name']])" data-confirm-method="POST" :data-confirm-url="route('admin.ui-kit.bulk', ['ids' => [$row['id']]])">{{ __('ui.delete') }}</x-ui::dropdown-item>
    </x-ui::row-actions>
</div>
