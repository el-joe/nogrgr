@php
    $isSelf = $actor && $member->is($actor);
    $editable = $canManage && ! $locked && ! $isSelf;
    $status = $member->status;
@endphp
<div class="flex items-center justify-end gap-1">
    <x-ui::icon-button icon="eye" size="sm" :label="__('identity::staff.view_named', ['name' => $member->name])" data-remote-modal="vendor-staff-view-modal" :data-url="vendor_route('settings.staff.show', ['staff' => $member->id])" />
    @if ($editable)
        <x-ui::icon-button icon="edit" size="sm" :label="__('identity::staff.edit_title', ['name' => $member->name])" data-remote-modal="vendor-staff-modal" :data-url="vendor_route('settings.staff.edit', ['staff' => $member->id])" />
        <x-ui::row-actions>
            @if ($status === \App\Modules\Identity\Enums\AccountStatus::Pending)
                <x-ui::dropdown-item icon="mail" :data-confirm="__('identity::staff.resend_message', ['email' => $member->email])" :data-confirm-title="__('identity::staff.resend')" :data-confirm-label="__('identity::staff.resend')" data-confirm-tone="brand" data-confirm-method="POST" :data-confirm-url="vendor_route('settings.staff.resend', ['staff' => $member->id])">{{ __('identity::staff.resend') }}</x-ui::dropdown-item>
            @elseif ($status === \App\Modules\Identity\Enums\AccountStatus::Active)
                <x-ui::dropdown-item icon="x-circle" :data-confirm="__('identity::staff.deactivate_message')" :data-confirm-title="__('identity::staff.deactivate_title', ['name' => $member->name])" :data-confirm-label="__('identity::staff.deactivate')" data-confirm-method="PATCH" :data-confirm-url="vendor_route('settings.staff.toggle', ['staff' => $member->id, 'is_active' => 0])">{{ __('identity::staff.deactivate') }}</x-ui::dropdown-item>
            @else
                <x-ui::dropdown-item icon="check-circle" :data-confirm="__('identity::staff.activate_message')" :data-confirm-title="__('identity::staff.activate_title', ['name' => $member->name])" :data-confirm-label="__('identity::staff.activate')" data-confirm-tone="brand" data-confirm-method="PATCH" :data-confirm-url="vendor_route('settings.staff.toggle', ['staff' => $member->id, 'is_active' => 1])">{{ __('identity::staff.activate') }}</x-ui::dropdown-item>
            @endif
            <x-ui::dropdown-divider />
            <x-ui::dropdown-item icon="trash" tone="danger" :data-confirm="__('identity::staff.delete_message')" :data-confirm-title="__('identity::staff.delete_title', ['name' => $member->name])" data-confirm-method="DELETE" :data-confirm-url="vendor_route('settings.staff.destroy', ['staff' => $member->id])">{{ __('identity::staff.delete') }}</x-ui::dropdown-item>
        </x-ui::row-actions>
    @elseif ($canManage && $locked)
        <span class="text-xs text-ink-secondary">{{ __('identity::staff.owner_locked_short') }}</span>
    @endif
</div>
