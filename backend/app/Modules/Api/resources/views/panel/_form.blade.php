{{--
    Mobile app settings form shared by Vendor › Your store › Mobile app (VEN-037 / 037a,
    Figma 200:37622 aligned to the Tenant Panel) and Admin › Vendor › Mobile app tab
    (ADM-041g): versions + store URL per platform, maintenance switch + message per store
    language, splash colour + logo, feature switches. The sidebar shows the store links
    state, push status and a live-less splash preview (SVG fill, no inline style).
    $page (MobileAppPanelService::page()), $action, $formId, $canManage, $pushNote (HTML-free text), optional $pushLink.
--}}
@php
    $settings = $page['settings'];
    $features = \App\Modules\Api\Services\MobileAppPanelService::featureOptions();
    $splashColor = $settings->splashBackground ?? '#0b0b0b';
@endphp
<form id="{{ $formId }}" method="POST" action="{{ $action }}" enctype="multipart/form-data" data-ajax data-unsaved-form novalidate class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_340px]">
    @csrf
    @method('PUT')
    <fieldset class="grid min-w-0 gap-6" @disabled(! $canManage)>
        <x-ui::card :title="__('api::panel.sections.releases')" :subtitle="__('api::panel.sections.releases_help')">
            <div class="grid gap-6">
                @foreach (['ios', 'android'] as $platform)
                    @php($release = $settings->release($platform))
                    <div class="grid gap-4" data-mobile-platform="{{ $platform }}">
                        <h3 class="text-sm font-semibold text-ink">{{ __('api::panel.platforms.'.$platform) }}</h3>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-ui::input :name="$platform.'_min_version'" :label="__('api::panel.fields.min_version')" :value="$release->minVersion" :placeholder="__('api::panel.placeholders.version')" maxlength="20" dir="ltr" />
                            <x-ui::input :name="$platform.'_latest_version'" :label="__('api::panel.fields.latest_version')" :value="$release->latestVersion" :placeholder="__('api::panel.placeholders.version')" maxlength="20" dir="ltr" />
                        </div>
                        <x-ui::input :name="$platform.'_store_url'" type="url" :label="__('api::panel.fields.store_url')" :value="$release->storeUrl" :placeholder="__('api::panel.placeholders.'.$platform.'_url')" maxlength="500" dir="ltr" />
                    </div>
                @endforeach
            </div>
        </x-ui::card>

        <x-ui::card :title="__('api::panel.sections.maintenance')" :subtitle="__('api::panel.sections.maintenance_help')">
            <div class="grid gap-4">
                <x-ui::switch name="maintenance" :label="__('api::panel.fields.maintenance')" :checked="$settings->maintenance" />
                @foreach ($page['locales'] as $locale)
                    <x-ui::textarea :name="'maintenance_message['.$locale['code'].']'" :label="__('api::panel.fields.maintenance_message').' · '.$locale['name']" :value="$settings->maintenanceMessage[$locale['code']] ?? null" :placeholder="__('api::panel.placeholders.message')" rows="2" maxlength="300" :dir="$locale['dir']" />
                @endforeach
            </div>
        </x-ui::card>

        <x-ui::card :title="__('api::panel.sections.splash')" :subtitle="__('api::panel.sections.splash_help')">
            <div class="grid gap-4">
                <x-ui::input name="splash_background" :label="__('api::panel.fields.splash_background')" :value="$settings->splashBackground" placeholder="#0B0B0B" maxlength="7" dir="ltr" />
                <x-ui::image-upload name="splash_logo[]" :label="__('api::panel.fields.splash_logo')" :max="1" accept="image/png,image/jpeg,image/webp"
                    :images="$page['logoUrl'] ? [['id' => 'current', 'url' => $page['logoUrl'], 'alt' => __('api::panel.fields.splash_logo')]] : []" />
            </div>
        </x-ui::card>

        <x-ui::card :title="__('api::panel.sections.features')" :subtitle="__('api::panel.sections.features_help')">
            <div class="grid gap-3 sm:grid-cols-2">
                @foreach ($features as $value => $label)
                    <x-ui::switch :name="'features['.$value.']'" :id="$formId.'-feature-'.$value" :label="$label" :checked="$page['features'][$value] ?? false" />
                @endforeach
            </div>
        </x-ui::card>
    </fieldset>

    <aside class="grid gap-6">
        <x-ui::card :title="__('api::panel.sections.links')">
            <dl class="grid gap-3 text-sm">
                @foreach ($page['platforms'] as $platform => $info)
                    <div class="flex items-center justify-between gap-3" data-store-link="{{ $platform }}">
                        <dt class="text-ink-secondary">
                            @if ($info['url'])
                                <a href="{{ $info['url'] }}" target="_blank" rel="noopener" class="hover:underline">{{ $info['label'] }}</a>
                            @else
                                {{ $info['label'] }}
                            @endif
                        </dt>
                        <dd>
                            @if ($info['state'] === 'live')
                                <x-ui::badge tone="success">{{ __('api::panel.states.live', ['version' => $info['live']]) }}</x-ui::badge>
                            @else
                                <x-ui::badge tone="neutral">{{ __('api::panel.states.not_set') }}</x-ui::badge>
                            @endif
                        </dd>
                    </div>
                @endforeach
            </dl>
        </x-ui::card>

        <x-ui::card :title="__('api::panel.sections.push')">
            <p class="flex items-start gap-2 text-sm text-ink" data-push-state="{{ $page['pushReady'] ? 'ready' : 'missing' }}">
                <x-ui::icon :name="$page['pushReady'] ? 'check-circle' : 'info'" :size="18" @class(['mt-0.5 shrink-0', 'text-success' => $page['pushReady'], 'text-info' => ! $page['pushReady']]) />
                <span>{{ $pushNote }}</span>
            </p>
            @if (! empty($pushLink))
                <div class="mt-3"><x-ui::button variant="secondary" size="sm" icon="settings" :href="$pushLink">{{ __('api::panel.push.admin_link') }}</x-ui::button></div>
            @endif
        </x-ui::card>

        <x-ui::card :title="__('api::panel.sections.preview')">
            <figure class="grid gap-2">
                <div class="relative mx-auto aspect-[9/16] w-44 overflow-hidden rounded-[24px] border border-line" data-splash-preview>
                    <svg class="absolute inset-0 size-full" viewBox="0 0 9 16" preserveAspectRatio="none" aria-hidden="true"><rect width="9" height="16" fill="{{ $splashColor }}" /></svg>
                    @if ($page['logoUrl'])
                        <img src="{{ $page['logoUrl'] }}" alt="{{ __('api::panel.fields.splash_logo') }}" class="absolute inset-0 m-auto size-20 object-contain" loading="lazy">
                    @endif
                </div>
                <figcaption class="text-center text-xs text-ink-secondary">{{ __('api::panel.sections.preview_caption') }} · <bdi dir="ltr">{{ strtoupper($splashColor) }}</bdi></figcaption>
            </figure>
        </x-ui::card>
    </aside>
</form>
