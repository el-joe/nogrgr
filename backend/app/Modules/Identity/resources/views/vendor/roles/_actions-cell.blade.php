@php($url = vendor_route('settings.roles.edit', ['role' => $role->id]))
<div class="flex items-center justify-end gap-1">
    <x-ui::icon-button :icon="$locked || ! $canManage ? 'eye' : 'edit'" size="sm" :label="$locked || ! $canManage ? __('identity::roles.actions.view') : __('identity::roles.actions.edit')" :href="$url" />
    @if ($canManage)
        <x-ui::row-actions>
            <x-ui::dropdown-item icon="copy" :href="vendor_route('settings.roles.create', ['from' => $role->id])">{{ __('identity::roles.actions.duplicate') }}</x-ui::dropdown-item>
            @unless ($locked)
                <x-ui::dropdown-divider />
                @if ($assigned > 0)
                    <x-ui::dropdown-item icon="trash" tone="danger" disabled aria-disabled="true" :title="__('identity::staff.roles.delete_blocked')">{{ __('identity::roles.actions.delete') }}</x-ui::dropdown-item>
                @else
                    <x-ui::dropdown-item icon="trash" tone="danger" :data-confirm="__('identity::roles.delete_message')" :data-confirm-title="__('identity::roles.delete_title', ['name' => $role->name])" data-confirm-method="DELETE" :data-confirm-url="vendor_route('settings.roles.destroy', ['role' => $role->id])">{{ __('identity::roles.actions.delete') }}</x-ui::dropdown-item>
                @endif
            @endunless
        </x-ui::row-actions>
    @endif
</div>
