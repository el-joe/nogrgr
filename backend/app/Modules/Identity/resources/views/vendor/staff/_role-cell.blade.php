@php($role = $member->roles->first()?->name)
@if ($locked)
    <x-ui::badge tone="brand">{{ __('identity::staff.owner') }}</x-ui::badge>
@elseif ($role !== null)
    <x-ui::badge tone="info">{{ \App\Shared\Support\Ui::roleLabel($role) }}</x-ui::badge>
@else
    <x-ui::badge tone="neutral">{{ __('identity::staff.no_role') }}</x-ui::badge>
@endif
