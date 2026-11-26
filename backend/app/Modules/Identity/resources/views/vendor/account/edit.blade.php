{{--
    Vendor › Settings › Account (VEN-070, Figma Vendor Panel 90:111315): Profile card (name,
    e-mail, phone with intl-tel-input, language) saved by the header button; Password card
    (current / new / confirm, its own form); Linked sign-in card for the store owner (Google /
    Apple of the owner account, managed in the owner portal).
--}}
@php
    /** @var \App\Modules\Identity\Models\VendorStaff $staff */
@endphp
<x-vendor::layout :title="__('identity::vendor_account.title')" page="vendor-account">
    <div class="grid gap-6">
        <x-ui::page-header :title="__('identity::vendor_account.title')" :breadcrumbs="[__('navigation.admin.settings') => null, __('identity::vendor_account.title') => null]">
            <x-slot:actions>
                <x-ui::button type="submit" form="vendor-account-form" icon="check">{{ __('identity::vendor_account.save') }}</x-ui::button>
            </x-slot:actions>
        </x-ui::page-header>

        <div class="grid items-start gap-6 lg:grid-cols-2">
            <x-ui::card :title="__('identity::vendor_account.profile')" id="vendor-account-profile">
                <form id="vendor-account-form" method="POST" action="{{ vendor_route('settings.account.update') }}" data-ajax data-unsaved-form novalidate class="grid gap-5">
                    @csrf
                    @method('PUT')
                    <x-ui::input name="name" :label="__('identity::vendor_account.fields.name')" :value="$staff->name" required maxlength="120" autocomplete="name" />
                    @if ($isOwner)
                        <x-ui::input name="email" type="email" :label="__('identity::vendor_account.fields.email')" :value="$staff->email" dir="ltr" disabled :helper="__('identity::vendor_account.fields.email_owner_help')" />
                    @else
                        <x-ui::input name="email" type="email" :label="__('identity::vendor_account.fields.email')" :value="$staff->email" required maxlength="191" dir="ltr" autocomplete="email" :helper="__('identity::vendor_account.fields.email_help')" />
                    @endif
                    <x-ui::phone name="phone" :label="__('identity::vendor_account.fields.phone')" :value="$staff->phone" required />
                    <x-ui::select name="locale" :label="__('identity::vendor_account.fields.language')" :options="$languages" :selected="$staff->locale ?? app()->getLocale()" :search="false" required />
                </form>
            </x-ui::card>

            <div class="grid gap-6">
                <x-ui::card :title="__('identity::vendor_account.password')" id="vendor-account-password">
                    <form method="POST" action="{{ vendor_route('settings.account.password') }}" data-ajax data-reset-on-success novalidate class="grid gap-5">
                        @csrf
                        @method('PUT')
                        <x-ui::input name="current_password" type="password" :label="__('identity::vendor_account.fields.current_password')" required autocomplete="current-password" dir="ltr" />
                        <x-ui::input name="password" type="password" :label="__('identity::vendor_account.fields.new_password')" :helper="$isOwner ? __('identity::vendor_account.fields.password_owner_help') : __('identity::vendor_account.fields.password_help')" required autocomplete="new-password" dir="ltr" minlength="8" />
                        <x-ui::input name="password_confirmation" type="password" :label="__('identity::vendor_account.fields.confirm_password')" required autocomplete="new-password" dir="ltr" minlength="8" />
                        <div class="flex justify-end">
                            <x-ui::button type="submit" variant="secondary" icon="key">{{ __('identity::vendor_account.change_password') }}</x-ui::button>
                        </div>
                    </form>
                </x-ui::card>

                @if ($linked !== null)
                    <x-ui::card :title="__('identity::vendor_account.linked')" id="vendor-account-linked">
                        <ul class="divide-y divide-line">
                            @foreach ($linked as $row)
                                <li class="flex items-center gap-3 py-3 first:pt-0">
                                    <span class="grid size-8 shrink-0 place-items-center rounded-full bg-subtle text-ink">
                                        <x-ui::icon :name="$row['provider']->value" :size="16" />
                                    </span>
                                    <span class="grid min-w-0 flex-1">
                                        <span class="text-sm font-medium text-ink">{{ $row['provider']->brand() }}</span>
                                        <span class="truncate text-xs text-ink-secondary" @if ($row['detail']) dir="ltr" @endif>{{ $row['detail'] ?? __('identity::vendor_account.not_linked') }}</span>
                                    </span>
                                    @if ($row['linked'])
                                        <x-ui::badge tone="success" dot>{{ __('identity::vendor_account.linked_badge') }}</x-ui::badge>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                        <p class="mt-3 text-xs text-ink-secondary">
                            {{ __('identity::vendor_account.linked_help') }}
                            <a href="{{ route('owner.profile.edit') }}" class="font-medium text-ink-brand underline" target="_blank" rel="noopener">{{ __('identity::vendor_account.manage_linked') }}</a>
                        </p>
                    </x-ui::card>
                @endif
            </div>
        </div>
    </div>
</x-vendor::layout>
