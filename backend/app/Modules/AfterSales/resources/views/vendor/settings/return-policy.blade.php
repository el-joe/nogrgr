{{--
    Vendor › Settings › Return policy (VEN-079, Figma Vendor Panel 90:117144): the store may
    be stricter than the Nogrgr default, never looser — return window (max = platform days),
    restocking fee (min = platform %), non-returnable categories (the platform's always stay),
    allow exchanges (only when the platform allows them) and the policy text shown on the
    storefront return form, per language.
--}}
@php
    /** @var \App\Modules\AfterSales\Models\ReturnPolicy $platform */
    /** @var \App\Modules\AfterSales\Models\ReturnPolicy $policy */
    $platformFee = rtrim(rtrim(number_format($platform->restocking_fee_bps / 100, 2, '.', ''), '0'), '.');
@endphp
<x-vendor::layout :title="__('after-sales::vendor_policy.title')" page="vendor-return-policy">
    <form id="vendor-return-policy-form" method="POST" action="{{ vendor_route('settings.return-policy.update') }}" data-ajax data-unsaved-form novalidate class="grid gap-6">
        @csrf
        @method('PUT')
        <x-ui::page-header :title="__('after-sales::vendor_policy.title')" :subtitle="__('after-sales::vendor_policy.subtitle', ['days' => $platform->window_days])" :breadcrumbs="[__('navigation.admin.settings') => null, __('after-sales::vendor_policy.title') => null]">
            @if ($canManage)
                <x-slot:actions>
                    <x-ui::button type="submit" icon="check">{{ __('after-sales::vendor_policy.save') }}</x-ui::button>
                </x-slot:actions>
            @endif
        </x-ui::page-header>

        <x-ui::card>
            <fieldset class="grid gap-5 md:grid-cols-2" @disabled(! $canManage)>
                <x-ui::input name="window_days" type="number" :label="__('after-sales::vendor_policy.fields.window')" :value="$policy->window_days" min="0" :max="$platform->window_days" dir="ltr" required :helper="__('after-sales::vendor_policy.fields.window_help', ['days' => $platform->window_days])" />
                <x-ui::input name="restocking_fee" type="number" :label="__('after-sales::vendor_policy.fields.fee')" :value="rtrim(rtrim(number_format($policy->restocking_fee_bps / 100, 2, '.', ''), '0'), '.')" :min="$platformFee" max="100" step="0.01" dir="ltr" required :helper="__('after-sales::vendor_policy.fields.fee_help', ['fee' => $platformFee])" />
                <x-ui::select name="non_returnable_category_ids" multiple class="md:col-span-2" :label="__('after-sales::vendor_policy.fields.categories')" :options="$categories" :selected="$policy->non_returnable_category_ids ?? []" :placeholder="__('after-sales::vendor_policy.fields.categories_placeholder')" :helper="($platform->non_returnable_category_ids ?? []) !== [] ? __('after-sales::vendor_policy.fields.categories_help') : null" />
                <div class="md:col-span-2">
                    <x-ui::switch name="allow_exchanges" :label="__('after-sales::vendor_policy.fields.exchanges')" :checked="$policy->allow_exchanges" :disabled="! $platform->allow_exchanges" :description="$platform->allow_exchanges ? null : __('after-sales::vendor_policy.fields.exchanges_off')" />
                </div>
                <div class="grid gap-3 md:col-span-2">
                    <h3 class="text-sm font-medium text-ink">{{ __('after-sales::vendor_policy.fields.text') }}</h3>
                    <x-ui::locale-tabs id="return-policy-text">
                        @foreach (\App\Shared\Support\Locale::enabled() as $code => $language)
                            <x-ui::locale-pane parent="return-policy-text" :locale="$code">
                                <x-ui::textarea :id="'return-policy-text-'.$code" :name="'text['.$code.']'" :label="__('after-sales::vendor_policy.fields.text_in', ['language' => $language['native']])" :value="$text[$code] ?? null" maxlength="5000" :rows="5" :dir="$language['dir'] ?? null" />
                            </x-ui::locale-pane>
                        @endforeach
                    </x-ui::locale-tabs>
                    <p class="text-xs text-ink-secondary">{{ __('after-sales::vendor_policy.fields.text_help') }}</p>
                </div>
            </fieldset>
        </x-ui::card>

        @unless ($customised)
            <p class="flex items-start gap-2 text-[13px] text-ink-secondary" role="status">
                <x-ui::icon name="info" :size="16" class="mt-0.5 shrink-0" />
                <span>{{ __('after-sales::vendor_policy.using_default') }}</span>
            </p>
        @endunless
    </form>
</x-vendor::layout>
