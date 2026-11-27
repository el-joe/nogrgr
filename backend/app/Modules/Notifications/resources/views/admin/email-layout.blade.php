{{--
    Admin › Settings › Email layout (MAIL-001, decision #118; layout after Figma Emails &
    printables 131:11159): branding (logo, brand + background colour), footer (text per
    locale, address, help / privacy links), social links and an EN / AR preview of a
    sample e-mail rendered through the real layout.
--}}
@php
    $logo = $settings['has_own_logo'] && $settings['logo_url'] ? [['id' => 'current', 'url' => $settings['logo_url'], 'alt' => __('notifications::layout.fields.logo')]] : [];
@endphp
<x-layouts.panel surface="admin" :title="__('notifications::layout.title')" data-page="admin-email-layout">
    <form id="email-layout-form" method="POST" action="{{ route('admin.settings.email-layout.update') }}" enctype="multipart/form-data" data-ajax data-unsaved-form novalidate class="grid gap-6">
        @csrf
        @method('PUT')

        <x-ui::page-header
            :title="__('notifications::layout.title')"
            :subtitle="__('notifications::layout.subtitle')"
            :breadcrumbs="[__('navigation.sections.system') => null, __('navigation.admin.settings') => null, __('notifications::templates.title') => route('admin.settings.email-templates.index'), __('notifications::layout.title') => null]"
        >
            <x-slot:actions>
                <x-ui::button variant="secondary" icon="mail" :href="route('admin.settings.email-templates.index')">{{ __('notifications::layout.templates') }}</x-ui::button>
                @if ($canManage)
                    <x-ui::button type="submit" icon="check">{{ __('notifications::layout.save') }}</x-ui::button>
                @endif
            </x-slot:actions>
        </x-ui::page-header>

        <div class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(0,0.9fr)]">
            <fieldset @disabled(! $canManage) class="grid min-w-0 gap-6">
                <x-ui::card>
                    <h2 class="ui-card-title mb-4 text-base font-semibold text-ink">{{ __('notifications::layout.branding') }}</h2>
                    <div class="grid gap-5">
                        <x-ui::image-upload name="logo" :label="__('notifications::layout.fields.logo')" :images="$logo" :max="1" :main="false" accept="image/png,image/jpeg,image/webp" :helper="__('notifications::layout.fields.logo_help')" />
                        <div class="grid gap-5 md:grid-cols-2">
                            <x-ui::input name="primary_color" type="color" :label="__('notifications::layout.fields.primary_color')" :value="$settings['primary_color']" :helper="__('notifications::layout.fields.primary_color_help')" required class="h-11" />
                            <x-ui::input name="background_color" type="color" :label="__('notifications::layout.fields.background_color')" :value="$settings['background_color']" required class="h-11" />
                        </div>
                    </div>
                </x-ui::card>

                <x-ui::card>
                    <h2 class="ui-card-title mb-4 text-base font-semibold text-ink">{{ __('notifications::layout.footer') }}</h2>
                    <div class="grid gap-5">
                        <x-ui::textarea name="footer[en]" :label="__('notifications::layout.fields.footer_en')" :value="$settings['footer']['en'] ?? ''" :helper="__('notifications::layout.fields.footer_help')" maxlength="300" rows="2" dir="ltr" />
                        <x-ui::textarea name="footer[ar]" :label="__('notifications::layout.fields.footer_ar')" :value="$settings['footer']['ar'] ?? ''" maxlength="300" rows="2" dir="rtl" />
                        <x-ui::input name="address" :label="__('notifications::layout.fields.address')" :value="$settings['address']" maxlength="191" />
                        <div class="grid gap-5 md:grid-cols-2">
                            <x-ui::input name="help_url" type="url" icon="link" :label="__('notifications::layout.fields.help_url')" :value="$settings['help_url']" dir="ltr" maxlength="255" />
                            <x-ui::input name="privacy_url" type="url" icon="link" :label="__('notifications::layout.fields.privacy_url')" :value="$settings['privacy_url']" dir="ltr" maxlength="255" />
                        </div>
                    </div>
                </x-ui::card>

                <x-ui::card>
                    <h2 class="ui-card-title mb-4 text-base font-semibold text-ink">{{ __('notifications::layout.social') }}</h2>
                    <div class="grid gap-5">
                        <x-ui::switch name="show_socials" :label="__('notifications::layout.fields.show_socials')" :checked="$settings['show_socials']" />
                        <div class="grid gap-5 md:grid-cols-2">
                            @foreach (\App\Modules\Integrations\Services\GeneralSettingsService::SOCIALS as $network)
                                <x-ui::input :name="'socials['.$network.']'" type="url" icon="link" :label="__('notifications::mail_layout.socials.'.$network)" :value="$settings['socials'][$network]" dir="ltr" maxlength="255" />
                            @endforeach
                        </div>
                    </div>
                </x-ui::card>
            </fieldset>

            <x-ui::card class="xl:sticky xl:top-20">
                <h2 class="ui-card-title mb-3 text-base font-semibold text-ink">{{ __('notifications::layout.preview') }}</h2>
                <x-ui::tabs id="email-layout-preview" variant="segmented" :label="__('notifications::templates.fields.locale')" :items="['en' => __('notifications::templates.locales.en'), 'ar' => __('notifications::templates.locales.ar')]">
                    @foreach (['en', 'ar'] as $locale)
                        <x-ui::tab-panel tabs="email-layout-preview" :key="$locale" :active="$loop->first" class="mt-3">
                            <iframe title="{{ __('notifications::layout.preview') }} ({{ __('notifications::templates.locales.'.$locale) }})" src="{{ route('admin.settings.email-layout.preview', ['locale' => $locale]) }}" sandbox loading="lazy" class="h-[620px] w-full rounded-control border border-line bg-white"></iframe>
                        </x-ui::tab-panel>
                    @endforeach
                </x-ui::tabs>
                <p class="mt-3 text-xs text-ink-secondary">{{ __('notifications::layout.preview_help') }}</p>
            </x-ui::card>
        </div>
    </form>
</x-layouts.panel>
