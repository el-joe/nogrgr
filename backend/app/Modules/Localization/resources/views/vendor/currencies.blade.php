{{--
    Vendor › Settings › Currencies (VEN-072, Figma Vendor Panel 90:112623): USD plus the
    currencies of the store's selling countries with the platform rate (1 USD =), the
    countries they are shown in, Default (shown automatically there) and Active (offered in
    the storefront switcher). Display only: every order is charged in USD.
--}}
<x-vendor::layout :title="__('localization::vendor_currencies.title')" page="vendor-currencies">
    <form id="vendor-currencies-form" method="POST" action="{{ vendor_route('settings.currencies.update') }}" data-ajax data-unsaved-form novalidate class="grid gap-6">
        @csrf
        @method('PUT')
        <x-ui::page-header :title="__('localization::vendor_currencies.title')" :subtitle="__('localization::vendor_currencies.subtitle')" :breadcrumbs="[__('navigation.admin.settings') => null, __('localization::vendor_currencies.title') => null]">
            @if ($canManage)
                <x-slot:actions>
                    <x-ui::button type="submit" icon="check">{{ __('localization::vendor_currencies.save') }}</x-ui::button>
                </x-slot:actions>
            @endif
        </x-ui::page-header>

        <x-ui::card :padding="false" class="overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[640px] text-start text-sm">
                    <thead class="bg-subtle text-xs text-ink-secondary">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-start font-medium">{{ __('localization::vendor_currencies.columns.currency') }}</th>
                            <th scope="col" class="px-4 py-3 text-end font-medium">{{ __('localization::vendor_currencies.columns.rate') }}</th>
                            <th scope="col" class="px-4 py-3 text-start font-medium">{{ __('localization::vendor_currencies.columns.shown_in') }}</th>
                            <th scope="col" class="px-4 py-3 text-start font-medium">{{ __('localization::vendor_currencies.columns.default') }}</th>
                            <th scope="col" class="px-4 py-3 text-start font-medium">{{ __('localization::vendor_currencies.columns.active') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($rows as $row)
                            @php($currency = $row['currency'])
                            <tr data-currency-row="{{ $currency->code }}">
                                <td class="px-4 py-3">
                                    <span class="grid">
                                        <span class="font-medium text-ink">{{ $currency->name }}</span>
                                        <bdi dir="ltr" class="text-xs text-ink-secondary">{{ $currency->code }}</bdi>
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-end font-semibold tabular-nums text-ink"><bdi dir="ltr">{{ number_format((float) $currency->rate, 4) }}</bdi></td>
                                <td class="px-4 py-3 text-ink">{{ $row['is_base'] ? __('localization::vendor_currencies.all_switcher') : implode(__('localization::vendor_currencies.list_separator'), $row['countries']) }}</td>
                                <td class="px-4 py-3">
                                    @if ($row['is_base'])
                                        <x-ui::badge tone="neutral">—</x-ui::badge>
                                    @else
                                        <x-ui::switch :id="'currency-default-'.$currency->code" name="default[]" :value="$currency->code" :checked="$row['default']" :unchecked-value="null" :disabled="! $canManage" :aria-label="__('localization::vendor_currencies.default_label', ['currency' => $currency->code])" data-currency-default />
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @if ($row['is_base'])
                                        <x-ui::badge tone="success">{{ __('localization::vendor_currencies.always_on') }}</x-ui::badge>
                                    @else
                                        <x-ui::switch :id="'currency-active-'.$currency->code" name="active[]" :value="$currency->code" :checked="$row['active']" :unchecked-value="null" :disabled="! $canManage" :aria-label="__('localization::vendor_currencies.active_label', ['currency' => $currency->code])" data-currency-active />
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if (count($rows) <= 1)
                <div class="border-t border-line">
                    <x-ui::empty-state compact :title="__('localization::vendor_currencies.empty_title')" :description="__('localization::vendor_currencies.empty_description')" />
                </div>
            @endif
        </x-ui::card>

        <p class="flex items-start gap-2 text-[13px] text-ink-secondary">
            <x-ui::icon name="info" :size="16" class="mt-0.5 shrink-0" />
            <span>
                {{ __('localization::vendor_currencies.usd_note') }}
                @if ($syncedAt)
                    {{ __('localization::vendor_currencies.synced', ['ago' => $syncedAt->locale(app()->getLocale())->diffForHumans()]) }}
                @endif
            </span>
        </p>
    </form>
</x-vendor::layout>
