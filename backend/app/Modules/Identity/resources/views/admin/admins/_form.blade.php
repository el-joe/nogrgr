{{--
    Admin create / edit form (ADM-110a, Figma Admin Panel 80:98485), loaded into
    #admin-modal. Submitted by the modal footer button (form="admin-form").
--}}
@php
    /** @var \App\Modules\Identity\Models\Admin|null $admin */
    $editing = $admin !== null;
    $title = $editing ? __('identity::admins.edit_title', ['name' => $admin->name]) : __('identity::admins.create_title');
    $isSelf = $editing && $admin->is(auth('admin')->user());
@endphp
<form id="admin-form" method="POST" action="{{ $editing ? route('admin.system.admins.update', $admin) : route('admin.system.admins.store') }}" data-ajax novalidate class="grid gap-5" data-modal-title="{{ $title }}" data-submit-label="{{ $editing ? __('identity::admins.actions.save') : __('identity::admins.actions.invite') }}">
    @csrf
    @if ($editing)
        @method('PUT')
    @endif
    <x-ui::input name="name" :label="__('identity::admins.fields.name')" :value="$admin?->name" required maxlength="120" autocomplete="off" data-autofocus />
    <x-ui::input name="email" type="email" :label="__('identity::admins.fields.email')" :value="$admin?->email" required maxlength="191" dir="ltr" autocomplete="off" />
    <x-ui::select name="role" :label="__('identity::admins.fields.role')" :options="$roleOptions" :selected="$role" :placeholder="__('identity::admins.fields.role')" required />
    <x-ui::input name="password" type="password" :label="__('identity::admins.fields.password')" :helper="$editing ? __('identity::admins.fields.password_keep') : __('identity::admins.fields.password_help')" :required="! $editing" autocomplete="new-password" dir="ltr" minlength="12" />
    <x-ui::input name="password_confirmation" type="password" :label="__('identity::admins.fields.password_confirmation')" :required="! $editing" autocomplete="new-password" dir="ltr" minlength="12" />
    @if ($isSelf)
        <input type="hidden" name="status" value="{{ \App\Modules\Identity\Enums\AccountStatus::Active->value }}">
    @else
        <x-ui::select name="status" :label="__('identity::admins.fields.status')" :options="\App\Modules\Identity\Enums\AccountStatus::options()" :selected="$admin?->status->value ?? \App\Modules\Identity\Enums\AccountStatus::Active->value" :search="false" :helper="__('identity::admins.fields.active_help')" required />
    @endif
</form>
