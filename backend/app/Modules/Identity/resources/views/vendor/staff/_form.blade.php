{{--
    Staff invite / edit (VEN-076a, Figma Tenant Panel 90:115176 "Invite staff"), loaded
    into #vendor-staff-modal: name, e-mail, phone (intl-tel-input, optional) and role
    (store roles, never "owner"). A new member gets an invitation e-mail to set a password.
--}}
@php
    /** @var \App\Modules\Identity\Models\VendorStaff|null $member */
    $editing = $member !== null;
@endphp
<form
    id="vendor-staff-form"
    method="POST"
    action="{{ $editing ? vendor_route('settings.staff.update', ['staff' => $member->id]) : vendor_route('settings.staff.store') }}"
    data-ajax
    novalidate
    class="grid gap-5"
    data-modal-title="{{ $title }}"
    data-submit-label="{{ $editing ? __('identity::staff.save') : __('identity::staff.send_invite') }}"
>
    @csrf
    @if ($editing)
        @method('PUT')
    @endif
    <x-ui::input name="name" :label="__('identity::staff.fields.name')" :value="$member?->name" required maxlength="120" autocomplete="off" data-autofocus />
    <x-ui::input name="email" type="email" :label="__('identity::staff.fields.email')" :value="$member?->email" required maxlength="191" dir="ltr" autocomplete="off" :helper="$editing ? __('identity::staff.fields.email_change_help') : null" />
    <x-ui::phone name="phone" id="vendor-staff-phone" :label="__('identity::staff.fields.phone')" :value="$member?->phone" />
    @if ($roleOptions === [])
        <p class="flex items-start gap-2 rounded-control bg-warning-bg px-3 py-2.5 text-[13px] text-warning">
            <x-ui::icon name="info" :size="16" class="mt-0.5 shrink-0" />
            <span>{{ __('identity::staff.no_roles') }}
                @if (auth('vendor')->user()?->can('settings.roles.manage'))
                    <a class="font-medium underline" href="{{ vendor_route('settings.roles.create') }}">{{ __('identity::staff.create_role') }}</a>
                @endif
            </span>
        </p>
    @endif
    <x-ui::select name="role" :label="__('identity::staff.fields.role')" :options="$roleOptions" :selected="$role" :placeholder="__('identity::staff.fields.role_placeholder')" required :helper="__('identity::staff.fields.role_help')" />
    @unless ($editing)
        <p class="flex items-start gap-2 rounded-control bg-info-bg px-3 py-2.5 text-[13px] text-info">
            <x-ui::icon name="mail" :size="16" class="mt-0.5 shrink-0" />
            <span>{{ __('identity::staff.invite_note') }}</span>
        </p>
    @endunless
</form>
