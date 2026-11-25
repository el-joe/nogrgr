{{--
    Admin › Logistics › Shipping › Settings (ADM-034, Figma Admin Panel 71:48071): defaults
    (min / max days used when no company day rule applies, order cut-off, weekly off days,
    storefront toggles) and the default company per country.
--}}
@php
    $canManage = (bool) auth('admin')->user()?->can('shipping.settings.manage');
@endphp
<x-layouts.panel surface="admin" :title="__('shipping::settings.title')" data-page="admin-shipping">
    <div class="grid gap-6">
        <x-ui::page-header
            :title="__('shipping::settings.title')"
            :subtitle="__('shipping::settings.subtitle')"
            :breadcrumbs="[__('navigation.sections.logistics') => null, __('navigation.admin.shipping') => null, __('shipping::settings.breadcrumb') => null]"
        />

        <div class="grid items-start gap-6 lg:grid-cols-2">
            <form id="shipping-settings-form" method="POST" action="{{ route('admin.shipping.settings.update') }}" data-ajax data-unsaved-form novalidate>
                @csrf
                @method('PUT')
                <fieldset @disabled(! $canManage)>
                    <x-ui::card :title="__('shipping::settings.defaults_title')">
                        <div class="grid gap-5">
                            <div class="grid gap-5 sm:grid-cols-2">
                                <x-ui::input name="default_min_days" type="number" min="0" max="60" step="1" :label="__('shipping::settings.fields.default_min_days')" :value="$settings->defaultMinDays" required dir="ltr" />
                                <x-ui::input name="default_max_days" type="number" min="1" max="90" step="1" :label="__('shipping::settings.fields.default_max_days')" :value="$settings->defaultMaxDays" required dir="ltr" />
                            </div>
                            <x-ui::input name="cutoff_time" type="time" :label="__('shipping::settings.fields.cutoff_time')" :value="$settings->cutoffTime" :helper="__('shipping::settings.fields.cutoff_help', ['timezone' => config('app.timezone')])" dir="ltr" />
                            <x-ui::select name="weekly_off_days" multiple :label="__('shipping::settings.fields.weekly_off_days')" :options="$weekdays" :selected="$settings->weeklyOffDays" :placeholder="__('shipping::settings.fields.no_off_days')" :helper="__('shipping::settings.fields.weekly_off_help')" />
                            <x-ui::switch name="show_estimate_on_product" :label="__('shipping::settings.fields.show_estimate')" :checked="$settings->showEstimateOnProduct" />
                            <x-ui::switch name="show_delivery_popup" :label="__('shipping::settings.fields.show_popup')" :checked="$settings->showDeliveryPopup" />
                        </div>
                    </x-ui::card>
                </fieldset>
                @if ($canManage)
                    <div data-unsaved-bar hidden class="sticky bottom-0 z-20 -mx-4 mt-6 flex flex-wrap items-center justify-between gap-3 border-t border-line bg-surface px-4 py-3 shadow-overlay sm:-mx-6 sm:px-6">
                        <p class="text-sm font-medium text-ink">{{ __('shipping::common.unsaved') }}</p>
                        <div class="flex gap-3">
                            <x-ui::button variant="secondary" data-unsaved-discard>{{ __('shipping::common.discard') }}</x-ui::button>
                            <x-ui::button type="submit" icon="check">{{ __('shipping::settings.save') }}</x-ui::button>
                        </div>
                    </div>
                @endif
            </form>

            <x-ui::card :title="__('shipping::settings.defaults.title')" :padding="false">
                @if ($canManage)
                    <x-slot:actions>
                        <x-ui::button variant="secondary" size="sm" icon="plus" data-overlay-open="default-company-modal">{{ __('shipping::settings.defaults.add') }}</x-ui::button>
                    </x-slot:actions>
                @endif
                @if ($defaults === [])
                    <div class="p-5">
                        <x-ui::empty-state compact icon="truck" :title="__('shipping::settings.defaults.empty_title')" :description="__('shipping::settings.defaults.empty_description')" />
                    </div>
                @else
                    <div class="relative overflow-x-auto">
                        <table class="w-full min-w-[420px] text-sm">
                            <thead class="bg-subtle text-xs text-ink-secondary">
                                <tr>
                                    <th scope="col" class="px-5 py-3 text-start font-medium">{{ __('shipping::settings.defaults.country') }}</th>
                                    <th scope="col" class="px-5 py-3 text-start font-medium">{{ __('shipping::settings.defaults.company') }}</th>
                                    <th scope="col" class="px-5 py-3 text-start font-medium">{{ __('shipping::settings.defaults.days') }}</th>
                                    <th scope="col" class="px-5 py-3"><span class="sr-only">{{ __('shipping::common.actions') }}</span></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-line">
                                @foreach ($defaults as $row)
                                    @php($country = $countryNames[$row['country_id']] ?? null)
                                    <tr>
                                        <td class="px-5 py-3 text-ink">{{ $country ? trim($country->flag.' '.$country->name) : $row['country_id'] }}</td>
                                        <td class="px-5 py-3"><a href="{{ route('admin.shipping.companies.show', $row['company_id']) }}" class="text-ink hover:text-ink-brand">{{ $row['company'] }}</a></td>
                                        <td class="px-5 py-3 text-ink-secondary" dir="ltr">{{ $row['min_days'] !== null ? $row['min_days'].'–'.$row['max_days'] : $settings->defaultMinDays.'–'.$settings->defaultMaxDays.' *' }}</td>
                                        <td class="px-5 py-3 text-end">
                                            @if ($canManage)
                                                <x-ui::icon-button icon="trash" size="sm" :label="__('shipping::settings.defaults.remove', ['country' => $country?->name])" :data-confirm="__('shipping::settings.defaults.remove_message')" :data-confirm-title="__('shipping::settings.defaults.remove', ['country' => $country?->name])" data-confirm-method="DELETE" :data-confirm-url="route('admin.shipping.settings.defaults.destroy', $row['country_id'])" />
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p class="px-5 py-3 text-xs text-ink-secondary">{{ __('shipping::settings.defaults.fallback_note') }}</p>
                @endif
            </x-ui::card>
        </div>
    </div>

    @if ($canManage)
        <x-ui::modal id="default-company-modal" :title="__('shipping::settings.defaults.add')" size="sm" close-on-success>
            <form id="default-company-form" method="POST" action="{{ route('admin.shipping.settings.defaults.store') }}" data-ajax novalidate class="grid gap-5">
                @csrf
                <x-ui::select name="country_id" :label="__('shipping::settings.defaults.country')" :options="$countries" :placeholder="__('shipping::settings.defaults.country')" required />
                <x-ui::select name="shipping_company_id" :label="__('shipping::settings.defaults.company')" :options="$companies" :placeholder="__('shipping::settings.defaults.company')" required />
                @if ($companies === [])
                    <p class="text-[13px] text-ink-secondary">{{ __('shipping::settings.defaults.no_companies') }} <a href="{{ route('admin.shipping.companies.index') }}" class="text-ink-brand underline">{{ __('shipping::companies.title') }}</a></p>
                @endif
            </form>
            <x-slot:footer>
                <x-ui::button variant="secondary" data-overlay-close>{{ __('shipping::common.cancel') }}</x-ui::button>
                <x-ui::button type="submit" form="default-company-form" icon="check">{{ __('shipping::common.save') }}</x-ui::button>
            </x-slot:footer>
        </x-ui::modal>
    @endif
</x-layouts.panel>
