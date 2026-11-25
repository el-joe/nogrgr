@php
    $canUpdate = $actor && $actor->can('update', $admin);
    $isSelf = $actor && $admin->is($actor);
    $active = $admin->status === \App\Modules\Identity\Enums\AccountStatus::Active;
@endphp
@if ($canUpdate)
    <div class="flex items-center justify-end gap-1">
        <x-ui::icon-button icon="edit" size="sm" :label="__('identity::admins.edit_title', ['name' => $admin->name])" data-remote-modal="admin-modal" :data-url="route('admin.system.admins.edit', $admin)" />
        @unless ($isSelf)
            <x-ui::row-actions>
                <x-ui::dropdown-item icon="edit" data-remote-modal="admin-modal" :data-url="route('admin.system.admins.edit', $admin)">{{ __('identity::admins.actions.edit') }}</x-ui::dropdown-item>
                @if ($active)
                    <x-ui::dropdown-item icon="x-circle" :data-confirm="__('identity::admins.deactivate_message')" :data-confirm-title="__('identity::admins.deactivate_title', ['name' => $admin->name])" :data-confirm-label="__('identity::admins.actions.deactivate')" data-confirm-method="PATCH" :data-confirm-url="route('admin.system.admins.toggle', [$admin, 'is_active' => 0])">{{ __('identity::admins.actions.deactivate') }}</x-ui::dropdown-item>
                @else
                    <x-ui::dropdown-item icon="check-circle" :data-confirm="__('identity::admins.activate_message')" :data-confirm-title="__('identity::admins.activate_title', ['name' => $admin->name])" :data-confirm-label="__('identity::admins.actions.activate')" data-confirm-tone="brand" data-confirm-method="PATCH" :data-confirm-url="route('admin.system.admins.toggle', [$admin, 'is_active' => 1])">{{ __('identity::admins.actions.activate') }}</x-ui::dropdown-item>
                @endif
                <x-ui::dropdown-divider />
                <x-ui::dropdown-item icon="trash" tone="danger" :data-confirm="__('identity::admins.delete_message')" :data-confirm-title="__('identity::admins.delete_title', ['name' => $admin->name])" data-confirm-method="DELETE" :data-confirm-url="route('admin.system.admins.destroy', $admin)">{{ __('ui.delete') }}</x-ui::dropdown-item>
            </x-ui::row-actions>
        @endunless
    </div>
@endif
