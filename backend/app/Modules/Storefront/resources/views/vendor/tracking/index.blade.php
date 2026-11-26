{{--
    Vendor › Settings › Tracking pixels (VEN-083, Figma Vendor Panel 91:116736): one card per
    network (Meta, TikTok, Snap, GA4) with its id, on / off and status (Receiving / Off /
    Not set); one Save. The storefront loads enabled pixels only after the shopper accepts
    tracking cookies (decision #91).
--}}
<x-vendor::layout :title="__('content::store_tracking.title')" page="vendor-tracking">
    <form method="POST" action="{{ vendor_route('settings.tracking.update') }}" data-ajax data-unsaved-form novalidate class="grid gap-6">
        @csrf
        @method('PUT')
        <x-ui::page-header :title="__('content::store_tracking.title')" :subtitle="__('content::store_tracking.subtitle')" :breadcrumbs="[__('content::store_tracking.crumb') => null, __('content::store_tracking.title') => null]">
            @if ($canManage)
                <x-slot:actions>
                    <x-ui::button type="submit" icon="check">{{ __('content::store_tracking.save') }}</x-ui::button>
                </x-slot:actions>
            @endif
        </x-ui::page-header>

        <div class="grid gap-6 md:grid-cols-2">
            @foreach ($pixels as $pixel)
                @php
                    $id = $settings->id($pixel);
                    $on = $settings->isEnabled($pixel);
                    $status = $id === null ? 'not_set' : ($on ? 'receiving' : 'off');
                @endphp
                <x-ui::card :id="'pixel-'.$pixel->value">
                    <fieldset class="grid gap-4" @disabled(! $canManage)>
                        <div class="flex items-center justify-between gap-3">
                            <legend class="text-base font-semibold text-ink">{{ $pixel->label() }}</legend>
                            <x-ui::status :status="$status === 'receiving' ? 'active' : 'inactive'">{{ __('content::store_tracking.status.'.$status) }}</x-ui::status>
                        </div>
                        <x-ui::input :name="'pixels['.$pixel->value.'][id]'" :label="__($pixel === \App\Modules\Storefront\Enums\TrackingPixel::Ga4 ? 'content::store_tracking.measurement_id' : 'content::store_tracking.pixel_id')" :value="$id" maxlength="64" dir="ltr" :placeholder="__('content::store_tracking.not_set')" :helper="__('content::store_tracking.help.'.$pixel->value)" autocomplete="off" spellcheck="false" />
                        <x-ui::switch :name="'pixels['.$pixel->value.'][enabled]'" :id="'pixel-'.$pixel->value.'-enabled'" :label="__('content::store_tracking.enabled')" :checked="$on" />
                    </fieldset>
                </x-ui::card>
            @endforeach
        </div>

        <div class="flex items-start gap-3 rounded-card bg-info-bg px-4 py-3 text-sm text-ink" role="note">
            <x-ui::icon name="shield" :size="16" class="mt-0.5 shrink-0 text-info" />
            <span class="grid gap-1"><span>{{ __('content::store_tracking.events') }}</span><span class="text-ink-secondary">{{ __('content::store_tracking.consent_note') }}</span></span>
        </div>
    </form>
</x-vendor::layout>
