@if (auth('admin')->user()?->can('roles.manage'))
    <div class="flex items-center justify-end gap-1">
        <x-ui::icon-button :icon="$locked ? 'eye' : 'edit'" size="sm" :label="$locked ? __('identity::roles.actions.view') : __('identity::roles.actions.edit')" :href="route('admin.system.roles.edit', $role)" />
        <x-ui::row-actions>
            <x-ui::dropdown-item :icon="$locked ? 'eye' : 'edit'" :href="route('admin.system.roles.edit', $role)">{{ $locked ? __('identity::roles.actions.view') : __('identity::roles.actions.edit') }}</x-ui::dropdown-item>
            <x-ui::dropdown-item icon="copy" :href="route('admin.system.roles.create', ['from' => $role->id])">{{ __('identity::roles.actions.duplicate') }}</x-ui::dropdown-item>
            @unless ($locked)
                <x-ui::dropdown-divider />
                @if ($assigned > 0)
                    <x-ui::dropdown-item icon="trash" tone="danger" disabled aria-disabled="true" :title="__('identity::roles.delete_blocked')">{{ __('identity::roles.actions.delete') }}</x-ui::dropdown-item>
                @else
                    <x-ui::dropdown-item icon="trash" tone="danger" :data-confirm="__('identity::roles.delete_message')" :data-confirm-title="__('identity::roles.delete_title', ['name' => $role->name])" data-confirm-method="DELETE" :data-confirm-url="route('admin.system.roles.destroy', $role)">{{ __('identity::roles.actions.delete') }}</x-ui::dropdown-item>
                @endif
            @endunless
        </x-ui::row-actions>
    </div>
@endif
