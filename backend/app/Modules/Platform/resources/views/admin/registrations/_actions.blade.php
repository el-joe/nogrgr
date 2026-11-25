{{--
    Pending registrations › row actions: view, and in the more menu resend completion link
    (only when the status allows a link, vendors.manage) and delete (vendors.delete).
--}}
@php
    $admin = auth('admin')->user();
    $canResend = (bool) $admin?->can('vendors.manage') && $registration->status->canSendLink();
    $canDelete = (bool) $admin?->can('vendors.delete') && $registration->status->isPending();
    $name = $registration->applicantName();
@endphp
<div class="flex items-center justify-end gap-1">
    <x-ui::icon-button icon="eye" size="sm" :href="route('admin.vendors.pending.show', $registration->id)" :label="__('platform::registrations.actions.view', ['name' => $name])" />
    @if ($canResend || $canDelete)
        <x-ui::row-actions :label="__('platform::registrations.actions.more', ['name' => $name])">
            @if ($canResend)
                <x-ui::dropdown-item
                    icon="mail"
                    :data-confirm="__('platform::registrations.actions.resend_message', ['email' => $registration->email])"
                    :data-confirm-title="__('platform::registrations.actions.resend_title')"
                    :data-confirm-label="__('platform::registrations.actions.resend')"
                    data-confirm-tone="primary"
                    data-confirm-method="POST"
                    :data-confirm-url="route('admin.vendors.pending.resend', $registration->id)"
                >{{ __('platform::registrations.actions.resend') }}</x-ui::dropdown-item>
            @endif
            @if ($canDelete)
                @if ($canResend)
                    <x-ui::dropdown-divider />
                @endif
                <x-ui::dropdown-item
                    icon="trash"
                    tone="danger"
                    :data-confirm="__('platform::registrations.actions.delete_message')"
                    :data-confirm-title="__('platform::registrations.actions.delete_title', ['email' => $registration->email])"
                    :data-confirm-label="__('platform::registrations.actions.delete')"
                    data-confirm-method="DELETE"
                    :data-confirm-url="route('admin.vendors.pending.destroy', $registration->id)"
                >{{ __('platform::registrations.actions.delete') }}</x-ui::dropdown-item>
            @endif
        </x-ui::row-actions>
    @endif
</div>
