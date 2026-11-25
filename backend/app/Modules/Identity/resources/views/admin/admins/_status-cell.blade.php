@php
    $active = $admin->status === \App\Modules\Identity\Enums\AccountStatus::Active;
    $canToggle = $actor && ! $admin->is($actor) && $actor->can('toggle', $admin);
@endphp
@if ($canToggle && $admin->status !== \App\Modules\Identity\Enums\AccountStatus::Pending)
    <x-ui::switch :name="'is_active_'.$admin->id" field="is_active" :url="route('admin.system.admins.toggle', $admin)" :checked="$active" :aria-label="__('identity::admins.toggle', ['name' => $admin->name])" />
@else
    <x-ui::status :status="$admin->status->value" />
@endif
