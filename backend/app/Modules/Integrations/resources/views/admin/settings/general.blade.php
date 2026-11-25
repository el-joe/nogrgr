{{--
    Admin › Settings › General (ADM-101, Figma Admin Panel 79:88365): Platform card
    (name ar/en, support e-mail + phone, timezone, archive days, default language),
    Social links, Branding (logo light, logo dark, favicon) and the sticky
    "Unsaved changes" bar.
--}}
@php
    $canManage = (bool) auth('admin')->user()?->can('settings.manage');
    $image = fn (string $key) => $settings['images'][$key] ? [['id' => 'current', 'url' => $settings['images'][$key], 'alt' => __('integrations::general.images.'.$key)]] : [];
@endphp
<x-layouts.panel surface="admin" :title="__('integrations::general.title')" data-page="admin-general-settings">
    <form id="general-settings-form" method="POST" action="{{ route('admin.settings.general.update') }}" enctype="multipart/form-data" data-ajax data-unsaved-form novalidate class="grid gap-6">
        @csrf
        @method('PUT')

        <x-ui::page-header
            :title="__('integrations::general.title')"
            :subtitle="__('integrations::general.subtitle')"
            :breadcrumbs="[__('navigation.sections.system') => null, __('navigation.admin.settings') => null, __('navigation.admin.general') => null]"
        />

        <fieldset @disabled(! $canManage) class="grid items-start gap-6 lg:grid-cols-2">
            <div class="grid gap-6">
                <x-ui::card>
                    <h2 class="mb-4 text-base font-semibold text-ink">{{ __('integrations::general.platform') }}</h2>
                    <div class="grid gap-5">
                        <div class="grid gap-5 md:grid-cols-2">
                            <x-ui::input name="platform_name[en]" :label="__('integrations::general.fields.platform_name_en')" :value="$settings['platform_name']['en'] ?? ''" required dir="ltr" maxlength="120" />
                            <x-ui::input name="platform_name[ar]" :label="__('integrations::general.fields.platform_name_ar')" :value="$settings['platform_name']['ar'] ?? ''" required dir="rtl" maxlength="120" />
                        </div>
                        <x-ui::input name="support_email" type="email" icon="mail" :label="__('integrations::general.fields.support_email')" :value="$settings['support_email']" dir="ltr" maxlength="191" />
                        <x-ui::phone name="support_phone" :label="__('integrations::general.fields.support_phone')" :value="$settings['support_phone']" :country="$settings['support_phone_country']" />
                        <x-ui::select name="timezone" :label="__('integrations::general.fields.timezone')" :options="$timezones" :selected="$settings['timezone']" required :helper="__('integrations::general.fields.timezone_help')" />
                        <x-ui::input name="archive_days" type="number" min="1" max="3650" :label="__('integrations::general.fields.archive_days')" :value="$settings['archive_days']" required dir="ltr" :helper="__('integrations::general.fields.archive_days_help')" />
                        <x-ui::select name="default_language" :label="__('integrations::general.fields.default_language')" :options="$languages" :selected="$settings['default_language']" required :helper="__('integrations::general.fields.default_language_help')" />
                    </div>
                </x-ui::card>

                <x-ui::card>
                    <h2 class="mb-4 text-base font-semibold text-ink">{{ __('integrations::general.social_links') }}</h2>
                    <div class="grid gap-5">
                        @foreach (\App\Modules\Integrations\Services\GeneralSettingsService::SOCIALS as $network)
                            <x-ui::input :name="'socials['.$network.']'" type="url" icon="link" :label="__('integrations::general.socials.'.$network)" :value="$settings['socials'][$network]" :placeholder="__('integrations::general.socials_placeholder.'.$network)" dir="ltr" maxlength="255" />
                        @endforeach
                    </div>
                </x-ui::card>
            </div>

            <x-ui::card>
                <h2 class="mb-1 text-base font-semibold text-ink">{{ __('integrations::general.branding') }}</h2>
                <p class="mb-4 text-[13px] text-ink-secondary">{{ __('integrations::general.branding_help') }}</p>
                <div class="grid gap-5 sm:grid-cols-3">
                    @foreach (\App\Modules\Integrations\Services\GeneralSettingsService::IMAGES as $key)
                        <x-ui::image-upload :name="$key" :label="__('integrations::general.images.'.$key)" :images="$image($key)" :max="1" :accept="$key === 'favicon' ? 'image/png' : 'image/png,image/jpeg,image/webp'" :helper="__('integrations::general.images_help.'.$key)" />
                    @endforeach
                </div>
            </x-ui::card>
        </fieldset>

        @if ($canManage)
            <div data-unsaved-bar hidden class="sticky bottom-0 z-20 -mx-4 flex flex-wrap items-center justify-between gap-3 border-t border-line bg-surface px-4 py-3 shadow-overlay sm:-mx-6 sm:px-6 xl:-mx-8 xl:px-8">
                <p class="text-sm font-medium text-ink">{{ __('integrations::general.unsaved') }}</p>
                <div class="flex gap-3">
                    <x-ui::button variant="secondary" data-unsaved-discard>{{ __('integrations::general.discard') }}</x-ui::button>
                    <x-ui::button type="submit" icon="check">{{ __('integrations::general.save') }}</x-ui::button>
                </div>
            </div>
        @endif
    </form>
</x-layouts.panel>
