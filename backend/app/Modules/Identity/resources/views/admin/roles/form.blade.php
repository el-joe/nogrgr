{{--
    Admin › Role create / edit (ADM-111a, Figma Admin Panel 80:91028): role name and the
    permission matrix grouped by module (rows = resources, columns = abilities).
    Ticking "Manage" ticks "View" and "View" cannot be cleared while "Manage" is on
    (resources/js/panels/admin/roles.js); the server applies the same rule.
    super-admin is shown read-only with every box ticked.
--}}
@php
    /** @var \Spatie\Permission\Models\Role|null $role */
    $editing = $role !== null;
    $title = $editing ? __('identity::roles.edit_title', ['name' => \App\Shared\Support\Ui::roleLabel($role->name)]) : __('identity::roles.create_title');
    $abilities = $matrix['abilities'];
    $all = \App\Modules\Identity\Support\PermissionCatalog::forGuard('admin');
@endphp
<x-layouts.panel surface="admin" :title="$title" data-page="admin-roles">
    <x-ui::page-header
        :title="$title"
        :subtitle="$locked ? __('identity::roles.locked') : __('identity::roles.subtitle')"
        :breadcrumbs="[__('navigation.sections.system') => null, __('identity::admins.breadcrumb') => route('admin.system.admins.index'), __('identity::roles.title') => route('admin.system.roles.index'), $title => null]"
    >
        <x-slot:actions>
            @if ($editing)
                <x-ui::button variant="secondary" icon="copy" :href="route('admin.system.roles.create', ['from' => $role->id])">{{ __('identity::roles.duplicate') }}</x-ui::button>
            @endif
            @unless ($locked)
                <x-ui::button type="submit" form="role-form" icon="check">{{ __('identity::roles.save') }}</x-ui::button>
            @endunless
        </x-slot:actions>
        <x-slot:tabs>
            @include('identity::admin._team-tabs', ['active' => 'roles'])
        </x-slot:tabs>
    </x-ui::page-header>

    <form id="role-form" method="POST" action="{{ $editing ? route('admin.system.roles.update', $role) : route('admin.system.roles.store') }}" data-ajax novalidate class="grid gap-6 *:min-w-0" data-role-matrix>
        @csrf
        @if ($editing)
            @method('PUT')
        @endif

        <x-ui::card>
            <div class="grid items-end gap-4 md:grid-cols-[minmax(0,420px)_1fr]">
                <x-ui::input name="name" :label="__('identity::roles.fields.name')" :value="$name" required maxlength="60" :disabled="$locked" />
                @if ($editing && $usersCount > 0)
                    <p class="flex items-center gap-2 text-[13px] text-ink-secondary">
                        <x-ui::icon name="users" :size="16" />
                        {{ trans_choice('identity::roles.assigned_notice', $usersCount, ['count' => $usersCount]) }}
                    </p>
                @endif
            </div>
        </x-ui::card>

        <input type="hidden" name="permissions" value="">
        <div class="relative overflow-x-auto rounded-card border border-line bg-surface shadow-card" data-error-key="permissions">
            <table class="w-full min-w-[640px] text-[13px]">
                <thead class="bg-subtle text-xs text-ink-secondary">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-start font-medium">{{ __('identity::roles.module') }}</th>
                        @foreach ($abilities as $ability)
                            <th scope="col" class="w-24 px-3 py-3 text-center font-medium">{{ \App\Modules\Identity\Support\PermissionMatrix::abilityLabel($ability) }}</th>
                        @endforeach
                        @unless ($locked)
                            <th scope="col" class="w-24 px-3 py-3 text-center font-medium"><span class="sr-only">{{ __('identity::roles.select_all') }}</span></th>
                        @endunless
                    </tr>
                </thead>
                @foreach ($matrix['modules'] as $module => $resources)
                    <tbody>
                        <tr class="border-t border-line">
                            <th scope="rowgroup" colspan="{{ count($abilities) + ($locked ? 1 : 2) }}" class="bg-canvas px-4 py-2 text-start text-xs font-medium text-ink-secondary">{{ \App\Modules\Identity\Support\PermissionMatrix::moduleLabel($module) }}</th>
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
                                                :disabled="$locked"
                                                :data-ability="$ability"
                                                :aria-label="__('identity::roles.permission_toggle', ['ability' => \App\Modules\Identity\Support\PermissionMatrix::abilityLabel($ability), 'resource' => $resourceLabel])"
                                            />
                                        @else
                                            <span class="inline-block size-[18px] rounded-[5px] border border-line bg-subtle align-middle" aria-hidden="true"></span>
                                        @endisset
                                    </td>
                                @endforeach
                                @unless ($locked)
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

        @unless ($locked)
            <div class="flex flex-wrap justify-between gap-3">
                <div class="flex gap-2">
                    <x-ui::button variant="ghost" size="sm" data-matrix-all="1">{{ __('identity::roles.select_all') }}</x-ui::button>
                    <x-ui::button variant="ghost" size="sm" data-matrix-all="0">{{ __('identity::roles.clear_all') }}</x-ui::button>
                </div>
                <div class="flex gap-3">
                    <x-ui::button variant="secondary" :href="route('admin.system.roles.index')">{{ __('ui.cancel') }}</x-ui::button>
                    <x-ui::button type="submit" icon="check">{{ __('identity::roles.save') }}</x-ui::button>
                </div>
            </div>
        @endunless
    </form>
</x-layouts.panel>
