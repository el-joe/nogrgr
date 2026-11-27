{{--
    Vendor › Settings › Mail (SMTP) (VEN-081, Figma Vendor Panel 91:115511): host, port,
    encryption, username, password (stored encrypted, never shown), from address / name;
    Save, "Send test email" (with the values on the page) and its result inline,
    "Use Nogrgr mail" to remove the server. Saving with host, username, password and from
    address all empty also switches back to Nogrgr mail (RQA-VEN-012).
--}}
<x-vendor::layout :title="__('notifications::vendor_mail.title')" page="vendor-mail">
    <form id="vendor-mail-form" method="POST" action="{{ vendor_route('settings.mail.update') }}" data-ajax data-unsaved-form novalidate class="grid gap-6" data-mail-form @if ($canManage) data-test-url="{{ vendor_route('settings.mail.test') }}" @endif>
        @csrf
        @method('PUT')
        <x-ui::page-header :title="__('notifications::vendor_mail.title')" :subtitle="__('notifications::vendor_mail.subtitle')" :breadcrumbs="[__('notifications::vendor_mail.crumb') => null, __('notifications::vendor_mail.title') => null]">
            @if ($canManage)
                <x-slot:actions>
                    @if ($settings['configured'])
                        <x-ui::button variant="ghost" :data-confirm="__('notifications::vendor_mail.use_platform_message')" :data-confirm-title="__('notifications::vendor_mail.use_platform_title')" data-confirm-method="DELETE" :data-confirm-label="__('notifications::vendor_mail.use_platform')" :data-confirm-url="vendor_route('settings.mail.destroy')">{{ __('notifications::vendor_mail.use_platform') }}</x-ui::button>
                    @endif
                    <x-ui::button variant="secondary" icon="mail" data-overlay-open="vendor-mail-test">{{ __('notifications::vendor_mail.send_test') }}</x-ui::button>
                    <x-ui::button type="submit" icon="check">{{ __('notifications::vendor_mail.save') }}</x-ui::button>
                </x-slot:actions>
            @endif
        </x-ui::page-header>

        <x-ui::card>
            <fieldset class="grid gap-5 md:grid-cols-2" @disabled(! $canManage)>
                <x-ui::input name="host" :label="__('notifications::vendor_mail.fields.host')" :value="$settings['host']" maxlength="191" dir="ltr" placeholder="smtp.zoho.com" autocomplete="off" />
                <x-ui::input name="port" type="number" :label="__('notifications::vendor_mail.fields.port')" :value="$settings['port']" min="1" max="65535" dir="ltr" required />
                <x-ui::select name="encryption" :label="__('notifications::vendor_mail.fields.encryption')" :options="$encryptions" :selected="$settings['encryption']" :search="false" required />
                <x-ui::input name="username" :label="__('notifications::vendor_mail.fields.username')" :value="$settings['username']" maxlength="191" dir="ltr" autocomplete="off" />
                <x-ui::input name="password" type="password" :label="__('notifications::vendor_mail.fields.password')" maxlength="191" dir="ltr" autocomplete="new-password" :placeholder="$settings['has_password'] ? '••••••••' : null" :helper="$settings['has_password'] ? __('notifications::vendor_mail.fields.password_saved') : null" />
                <x-ui::input name="from_name" :label="__('notifications::vendor_mail.fields.from_name')" :value="$settings['from_name']" maxlength="120" />
                <x-ui::input name="from_address" type="email" :label="__('notifications::vendor_mail.fields.from_address')" :value="$settings['from_address']" maxlength="191" dir="ltr" class="md:col-span-2" />
            </fieldset>
            <div class="mt-5 flex items-center gap-3 rounded-control bg-subtle px-4 py-3 text-sm text-ink" role="status" data-mail-status>
                <x-ui::icon name="info" :size="16" class="shrink-0 text-ink-secondary" />
                <span>{{ $settings['configured'] ? __('notifications::vendor_mail.status.own', ['address' => $settings['from_address'], 'host' => $settings['host']]) : __('notifications::vendor_mail.status.platform') }}</span>
            </div>
            <div class="mt-3 flex items-center gap-3 rounded-control px-4 py-3 text-sm" role="status" aria-live="polite" data-mail-result hidden>
                <x-ui::icon name="check-circle" :size="18" class="shrink-0" data-result-icon="success" />
                <x-ui::icon name="alert-triangle" :size="18" class="shrink-0" data-result-icon="error" />
                <span data-mail-result-text></span>
            </div>
        </x-ui::card>
    </form>

    @if ($canManage)
        <x-ui::modal id="vendor-mail-test" :title="__('notifications::vendor_mail.test_title')" :subtitle="__('notifications::vendor_mail.test_help')">
            <x-ui::input name="test_email" type="email" :label="__('notifications::vendor_mail.fields.test_email')" :value="$testEmail" maxlength="191" dir="ltr" required data-mail-test-email />
            <x-slot:footer>
                <x-ui::button variant="secondary" data-overlay-close>{{ __('notifications::vendor_mail.cancel') }}</x-ui::button>
                <x-ui::button icon="mail" data-mail-test-send>{{ __('notifications::vendor_mail.send') }}</x-ui::button>
            </x-slot:footer>
        </x-ui::modal>
    @endif
</x-vendor::layout>
