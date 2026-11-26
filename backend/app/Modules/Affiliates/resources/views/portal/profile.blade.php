{{--
    Affiliate › Profile (AFF-013, Figma Affiliate & Owner 121:102088): tabs Profile, Payout
    details (AFF-013a, 121:102328 — PayPal or bank transfer; the IBAN is stored encrypted)
    and Change password (AFF-013b, 121:102571). Each tab saves with its own AJAX form.
    Inputs: $affiliate, $locales, $methods.
--}}
@php
    $method = old('payout_method', $affiliate->payout_method?->value ?? \App\Modules\Affiliates\Enums\PayoutMethod::Paypal->value);
@endphp
<x-layouts.panel surface="affiliate" :title="__('affiliates::panel.profile.title')" data-page="affiliate-profile">
    <div class="grid gap-6">
        <x-ui::page-header :title="__('affiliates::panel.profile.title')" :subtitle="__('affiliates::panel.profile.subtitle')">
            <x-slot:tabs>
                <x-ui::tabs id="profile-tabs" :label="__('affiliates::panel.profile.title')" :items="[
                    'profile' => __('affiliates::panel.profile.tabs.profile'),
                    'payout' => __('affiliates::panel.profile.tabs.payout'),
                    'password' => __('affiliates::panel.profile.tabs.password'),
                ]" />
            </x-slot:tabs>
        </x-ui::page-header>

        <x-ui::tab-panel tabs="profile-tabs" key="profile" :active="! in_array(request()->query('tab'), ['payout', 'password'], true)">
            <x-ui::card :title="__('affiliates::panel.profile.personal')" :subtitle="__('affiliates::panel.profile.personal_hint')">
                <form method="POST" action="{{ route('affiliate.profile.update') }}" data-ajax class="grid max-w-xl gap-5" novalidate>
                    @csrf
                    @method('PUT')
                    <x-ui::input name="name" :label="__('affiliates::panel.profile.name')" :value="$affiliate->name" required maxlength="120" autocomplete="name" />
                    <x-ui::input name="email" type="email" :label="__('affiliates::panel.profile.email')" :value="$affiliate->email" dir="ltr" disabled :helper="__('affiliates::panel.profile.email_hint')" />
                    <x-ui::phone name="phone" :label="__('affiliates::panel.profile.phone')" :value="$affiliate->phone" />
                    <x-ui::select name="locale" :label="__('affiliates::panel.profile.language')" :options="$locales" :selected="$affiliate->locale ?? app()->getLocale()" :search="false" required />
                    <dl class="grid gap-3 rounded-control bg-subtle p-4 text-sm sm:grid-cols-2">
                        <div class="grid gap-0.5">
                            <dt class="text-xs text-ink-secondary">{{ __('affiliates::panel.links.code') }}</dt>
                            <dd class="font-semibold text-ink" dir="ltr">{{ $affiliate->code }}</dd>
                        </div>
                        <div class="grid gap-0.5">
                            <dt class="text-xs text-ink-secondary">{{ __('affiliates::panel.profile.type') }}</dt>
                            <dd class="font-semibold text-ink">{{ $affiliate->type->label() }}</dd>
                        </div>
                    </dl>
                    <div class="flex justify-end">
                        <x-ui::button type="submit">{{ __('affiliates::panel.profile.save') }}</x-ui::button>
                    </div>
                </form>
            </x-ui::card>
        </x-ui::tab-panel>

        <x-ui::tab-panel tabs="profile-tabs" key="payout" :active="request()->query('tab') === 'payout'">
            <x-ui::card :title="__('affiliates::panel.profile.payout_title')" :subtitle="__('affiliates::panel.profile.payout_hint')">
                <form method="POST" action="{{ route('affiliate.profile.payout') }}" data-ajax class="grid max-w-xl gap-5" data-payout-details novalidate>
                    @csrf
                    @method('PUT')
                    <x-ui::select name="payout_method" :label="__('affiliates::panel.profile.method')" :options="$methods" :selected="$method" :search="false" required data-payout-method />
                    <div class="grid gap-5" data-payout-fields="paypal" @if ($method !== 'paypal') hidden @endif>
                        <x-ui::input name="paypal_email" type="email" :label="__('affiliates::panel.profile.paypal_email')" :value="$affiliate->paypal_email" dir="ltr" maxlength="191" autocomplete="email" />
                    </div>
                    <div class="grid gap-5" data-payout-fields="bank_transfer" @if ($method !== 'bank_transfer') hidden @endif>
                        <x-ui::input name="bank_name" :label="__('affiliates::panel.profile.bank_name')" :value="$affiliate->bank_name" maxlength="120" />
                        <x-ui::input name="bank_account_name" :label="__('affiliates::panel.profile.bank_account_name')" :value="$affiliate->bank_account_name" maxlength="120" autocomplete="name" />
                        <x-ui::input name="bank_iban" :label="__('affiliates::panel.profile.bank_iban')" :value="$affiliate->bank_iban" dir="ltr" maxlength="40" :helper="__('affiliates::panel.profile.iban_hint')" />
                    </div>
                    <p class="flex items-start gap-2 rounded-control bg-info-bg px-4 py-3 text-xs text-ink">
                        <x-ui::icon name="lock" :size="16" class="shrink-0 text-info" />{{ __('affiliates::panel.profile.secure_hint') }}
                    </p>
                    <div class="flex justify-end">
                        <x-ui::button type="submit">{{ __('affiliates::panel.profile.save_payout') }}</x-ui::button>
                    </div>
                </form>
            </x-ui::card>
        </x-ui::tab-panel>

        <x-ui::tab-panel tabs="profile-tabs" key="password" :active="request()->query('tab') === 'password'">
            <x-ui::card :title="__('affiliates::panel.profile.password_title')">
                <form method="POST" action="{{ route('affiliate.profile.password') }}" data-ajax data-reset-on-success class="grid max-w-xl gap-5" novalidate>
                    @csrf
                    @method('PUT')
                    <x-ui::input name="current_password" type="password" :label="__('affiliates::panel.profile.current_password')" required autocomplete="current-password" />
                    <x-ui::input name="password" type="password" :label="__('affiliates::panel.profile.new_password')" required autocomplete="new-password" :helper="__('affiliates::panel.register.password_hint')" />
                    <x-ui::input name="password_confirmation" type="password" :label="__('affiliates::panel.profile.confirm_password')" required autocomplete="new-password" />
                    <div class="flex justify-end">
                        <x-ui::button type="submit">{{ __('affiliates::panel.profile.update_password') }}</x-ui::button>
                    </div>
                </form>
            </x-ui::card>
        </x-ui::tab-panel>
    </div>
</x-layouts.panel>
