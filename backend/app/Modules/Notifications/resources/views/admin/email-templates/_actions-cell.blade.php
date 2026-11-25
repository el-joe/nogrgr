@php $canManage = (bool) auth('admin')->user()?->can('email-templates.manage'); @endphp
<div class="flex items-center justify-end gap-1">
    <x-ui::icon-button icon="eye" size="sm" :label="__('notifications::templates.preview_named', ['name' => $template->name()])" data-template-preview="{{ route('admin.settings.email-templates.preview', $template) }}" />
    @if ($canManage)
        <x-ui::icon-button icon="edit" size="sm" :label="__('notifications::templates.edit_named', ['name' => $template->name()])" :href="route('admin.settings.email-templates.edit', $template)" />
        <x-ui::row-actions>
            <x-ui::dropdown-item icon="mail" data-template-test="{{ route('admin.settings.email-templates.test', $template) }}">{{ __('notifications::templates.send_test') }}</x-ui::dropdown-item>
            <x-ui::dropdown-item icon="refresh" :data-confirm="__('notifications::templates.sync_one_message')" :data-confirm-title="__('notifications::templates.sync_one_title', ['name' => $template->name()])" :data-confirm-label="__('notifications::templates.sync')" data-confirm-tone="brand" data-confirm-method="POST" :data-confirm-url="route('admin.settings.email-templates.sync', $template)">{{ __('notifications::templates.sync') }}</x-ui::dropdown-item>
            <x-ui::dropdown-divider />
            <x-ui::dropdown-item icon="trash" tone="danger" :data-confirm="__('notifications::templates.delete_message')" :data-confirm-title="__('notifications::templates.delete_title', ['name' => $template->name()])" data-confirm-method="DELETE" :data-confirm-url="route('admin.settings.email-templates.destroy', $template)">{{ __('ui.delete') }}</x-ui::dropdown-item>
        </x-ui::row-actions>
    @endif
</div>
