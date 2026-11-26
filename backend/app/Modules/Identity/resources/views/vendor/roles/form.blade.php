{{--
    Vendor › Role create / edit (VEN-077a, Figma Tenant Panel 90:115822 "Role: Packer"):
    role name + the store permission matrix grouped by panel area (rows = resources,
    columns = abilities). "Deny by default — unchecked areas are hidden." Ticking
    "Manage" ticks "View" (resources/js/panels/admin/roles.js, shared); the server applies
    the same rule. The built-in owner role, and every role for staff without
    settings.roles.manage, is shown read-only.
--}}
@php
    /** @var \Spatie\Permission\Models\Role|null $role */
    $editing = $role !== null;
    $readOnly = $locked || ! auth('vendor')->user()?->can('settings.roles.manage');
    $title = $editing ? __('identity::roles.edit_title', ['name' => \App\Shared\Support\Ui::roleLabel($role->name)]) : __('identity::roles.create_title');
    $abilities = $matrix['abilities'];
    $index = vendor_route('settings.roles.index');
@endphp
<x-vendor::layout :title="$title" page="vendor-staff">
    <div class="grid gap-6">
        <x-ui::page-header
            :title="$title"
            :subtitle="$locked ? __('identity::staff.roles.owner_locked') : __('identity::staff.roles.editor_subtitle')"
            :breadcrumbs="[__('navigation.admin.settings') => null, __('identity::roles.title') => $index, $title => null]"
        >
            <x-slot:actions>
                @if ($editing && auth('vendor')->user()?->can('settings.roles.manage'))
                    <x-ui::button variant="secondary" icon="copy" :href="vendor_route('settings.roles.create', ['from' => $role->id])">{{ __('identity::roles.duplicate') }}</x-ui::button>
                @endif
                @unless ($readOnly)
                    <x-ui::button type="submit" form="vendor-role-form" icon="check">{{ __('identity::roles.save') }}</x-ui::button>
                @endunless
            </x-slot:actions>
        </x-ui::page-header>

        <form id="vendor-role-form" method="POST" action="{{ $editing ? vendor_route('settings.roles.update', ['role' => $role->id]) : vendor_route('settings.roles.store') }}" data-ajax novalidate class="grid gap-6 *:min-w-0" data-role-matrix>
            @csrf
            @if ($editing)
                @method('PUT')
            @endif

            <x-ui::card>
                <div class="grid items-end gap-4 md:grid-cols-[minmax(0,420px)_1fr]">
                    <x-ui::input name="name" :label="__('identity::roles.fields.name')" :value="$name" required maxlength="60" :disabled="$readOnly" />
                    @if ($editing && $usersCount > 0)
                        <p class="flex items-center gap-2 text-[13px] text-ink-secondary">
                            <x-ui::icon name="users" :size="16" />
                            {{ trans_choice('identity::staff.roles.assigned_notice', $usersCount, ['count' => $usersCount]) }}
                        </p>
                    @endif
                </div>
            </x-ui::card>

            <input type="hidden" name="permissions" value="">
            <div class="relative overflow-x-auto rounded-card border border-line bg-surface shadow-card" data-error-key="permissions">
                <table data-lead class="vnd-table w-full min-w-[640px] text-[13px]">
                    <thead class="bg-subtle text-xs text-ink-secondary">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-start font-medium">{{ __('identity::roles.module') }}</th>
                            @foreach ($abilities as $ability)
                                <th scope="col" class="w-24 px-3 py-3 text-center font-medium">{{ \App\Modules\Identity\Support\PermissionMatrix::abilityLabel($ability) }}</th>
                            @endforeach
                            @unless ($readOnly)
                                <th scope="col" class="w-24 px-3 py-3 text-center font-medium"><span class="sr-only">{{ __('identity::roles.select_all') }}</span></th>
                            @endunless
                        </tr>
                    </thead>
                    @foreach ($matrix['modules'] as $module => $resources)
                        <tbody>
                            <tr class="border-t border-line">
                                <th scope="rowgroup" colspan="{{ count($abilities) + ($readOnly ? 1 : 2) }}" class="bg-canvas px-4 py-2 text-start text-xs font-medium text-ink-secondary">{{ \App\Modules\Identity\Support\PermissionMatrix::moduleLabel($module) }}</th>
                            </tr>
                            @foreach ($resources as $resource => $permissions)
                                @php($resourceLabel = \App\Modules\Identity\Support\PermissionMatrix::resourceLabel($resource))
                                <tr class="border-t border-line" data-matrix-row>
                                    <th scope="row" class="px-4 py-3 text-start font-normal text-ink">{{ $resourceLabel }}</th>
                                    @foreach ($abilities as $ability)
                                        <td class="px-3 py-3 text-center">
                                            @isset($permissions[$ability])
                                                <x-ui::checkbox
                                                    name="permissions[]"
                                                    :value="$permissions[$ability]"
                                                    :id="'perm-'.\Illuminate\Support\Str::slug($permissions[$ability])"
                                                    :checked="$locked || in_array($permissions[$ability], $granted, true)"
                                                    :disabled="$readOnly"
                                                    :data-ability="$ability"
                                                    :aria-label="__('identity::roles.permission_toggle', ['ability' => \App\Modules\Identity\Support\PermissionMatrix::abilityLabel($ability), 'resource' => $resourceLabel])"
                                                />
                                            @else
                                                <span class="inline-block size-[18px] rounded-[5px] border border-line bg-subtle align-middle" aria-hidden="true"></span>
                                            @endisset
                                        </td>
                                    @endforeach
                                    @unless ($readOnly)
                                        <td class="px-3 py-3 text-center">
                                            <button type="button" class="text-xs font-medium text-ink-brand hover:underline" data-matrix-row-toggle>{{ __('identity::roles.select_all') }}</button>
                                            <span class="sr-only">{{ __('identity::roles.select_row', ['resource' => $resourceLabel]) }}</span>
                                        </td>
                                    @endunless
                                </tr>
                            @endforeach
                        </tbody>
                    @endforeach
                </table>
            </div>

            @unless ($readOnly)
                <div class="flex flex-wrap justify-between gap-3">
                    <div class="flex gap-2">
                        <x-ui::button variant="ghost" size="sm" data-matrix-all="1">{{ __('identity::roles.select_all') }}</x-ui::button>
                        <x-ui::button variant="ghost" size="sm" data-matrix-all="0">{{ __('identity::roles.clear_all') }}</x-ui::button>
                    </div>
                    <div class="flex gap-3">
                        <x-ui::button variant="secondary" :href="$index">{{ __('ui.cancel') }}</x-ui::button>
                        <x-ui::button type="submit" icon="check">{{ __('identity::roles.save') }}</x-ui::button>
                    </div>
                </div>
            @endunless
        </form>
    </div>
</x-vendor::layout>
