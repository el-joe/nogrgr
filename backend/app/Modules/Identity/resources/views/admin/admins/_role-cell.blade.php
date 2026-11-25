@php
    $role = $admin->roles->first()?->name;
@endphp
@if ($role)
    <x-ui::badge :tone="$role === 'super-admin' ? 'brand' : 'info'" dot>{{ \App\Shared\Support\Ui::roleLabel($role) }}</x-ui::badge>
@else
    <span class="text-ink-muted">{{ __('identity::admins.no_role') }}</span>
@endif
