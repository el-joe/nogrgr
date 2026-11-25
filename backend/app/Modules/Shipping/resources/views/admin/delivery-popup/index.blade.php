{{--
    Admin › Logistics › Shipping › Delivery popup days (ADM-035, Figma Admin Panel 54:19015,
    invalid state 54:19898): share of orders delivered on day 1–10 after purchase, per
    country (or all countries); integers 0–100 validated on every keystroke, Save only when
    the total is exactly 100 %; 0 % days are hidden on the storefront (live preview).
--}}
@php
    $canManage = (bool) auth('admin')->user()?->can('shipping.delivery-popup.manage');
    $total = array_sum($percentages);
@endphp
<x-layouts.panel surface="admin" :title="__('shipping::popup.title')" data-page="admin-shipping">
    <form id="popup-form" method="POST" action="{{ route('admin.shipping.delivery-popup.update') }}" data-ajax data-unsaved-form data-popup-form novalidate class="grid gap-6">
        @csrf
        @method('PUT')
        <input type="hidden" name="country_id" value="{{ $country?->id }}">
        <x-ui::page-header
            :title="__('shipping::popup.title')"
            :subtitle="__('shipping::popup.subtitle')"
            :breadcrumbs="[__('navigation.sections.logistics') => null, __('navigation.admin.shipping') => null, __('shipping::popup.title') => null]"
        >
            @if ($canManage)
                <x-slot:actions>
                    <x-ui::button variant="secondary" icon="refresh" :data-confirm="__('shipping::popup.reset_message')" :data-confirm-title="__('shipping::popup.reset')" data-confirm-tone="warning" data-confirm-method="DELETE" :data-confirm-url="route('admin.shipping.delivery-popup.reset', array_filter(['country_id' => $country?->id]))">{{ __('shipping::popup.reset') }}</x-ui::button>
                    <x-ui::button type="submit" icon="check" data-popup-save :disabled="$total !== 100">{{ __('shipping::common.save') }}</x-ui::button>
                </x-slot:actions>
            @endif
        </x-ui::page-header>

        <div class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
            <x-ui::card :padding="false">
                <div class="flex flex-wrap items-start justify-between gap-3 border-b border-line px-5 py-4">
                    <div class="grid gap-0.5">
                        <h2 class="text-base font-semibold text-ink">{{ __('shipping::popup.distribution') }}</h2>
                        <p class="text-xs text-ink-secondary">{{ $country ? ($own ? __('shipping::popup.scope_own', ['country' => $country->name]) : __('shipping::popup.scope_inherited', ['country' => $country->name])) : __('shipping::popup.scope_all') }}</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        <div class="w-56" data-popup-country data-url="{{ route('admin.shipping.delivery-popup.index') }}" data-unsaved-ignore>
                            <x-ui::select name="country_switch" :label="__('shipping::popup.country')" label-hidden :options="$countries" :selected="$country?->id" :placeholder="__('shipping::popup.all_countries')" allow-clear />
                        </div>
                        @if ($canManage)
                            <x-ui::button variant="ghost" size="sm" icon="refresh" data-popup-even>{{ __('shipping::popup.distribute') }}</x-ui::button>
                        @endif
                    </div>
                </div>
                <fieldset @disabled(! $canManage)>
                    <legend class="sr-only">{{ __('shipping::popup.distribution') }}</legend>
                    <ul class="divide-y divide-line" role="list">
                        @foreach ($percentages as $day => $percentage)
                            <li class="grid grid-cols-[88px_minmax(0,1fr)_96px] items-center gap-4 px-5 py-3 sm:grid-cols-[96px_minmax(0,1fr)_104px_160px]" data-popup-row data-day="{{ $day }}">
                                <div class="grid">
                                    <label for="popup-day-{{ $day }}" class="text-sm font-medium text-ink">{{ __('shipping::popup.day', ['day' => $day]) }}</label>
                                    <span class="text-xs text-ink-secondary">{{ $day === 1 ? __('shipping::popup.next_day') : __('shipping::popup.plus_days', ['days' => $day]) }}</span>
                                </div>
                                <div class="h-2 overflow-hidden rounded-full bg-subtle" aria-hidden="true"><div class="h-full rounded-full bg-primary transition-[width] duration-200" data-popup-bar></div></div>
                                <div class="ui-control h-10 px-3">
                                    <input id="popup-day-{{ $day }}" type="number" name="days[{{ $day }}]" value="{{ $percentage }}" min="0" max="100" step="1" inputmode="numeric" dir="ltr" class="w-full text-end" data-popup-input>
                                    <span class="text-ink-muted">%</span>
                                </div>
                                <span class="col-span-3 sm:col-span-1" data-popup-state>
                                    <span data-state-shown @if ($percentage === 0) hidden @endif><x-ui::status status="active">{{ __('shipping::popup.shown') }}</x-ui::status></span>
                                    <span data-state-hidden @if ($percentage !== 0) hidden @endif><x-ui::status status="inactive">{{ __('shipping::popup.hidden') }}</x-ui::status></span>
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </fieldset>
                <div data-popup-total-row @class(['grid grid-cols-[88px_minmax(0,1fr)_auto] items-center gap-4 rounded-b-card border-t border-line px-5 py-4 sm:grid-cols-[96px_minmax(0,1fr)_104px_160px]', 'bg-success-bg' => $total === 100, 'bg-danger-bg' => $total !== 100])>
                    <span class="text-sm font-semibold text-ink">{{ __('shipping::popup.total') }}</span>
                    <div class="h-2 overflow-hidden rounded-full bg-surface" aria-hidden="true"><div class="h-full rounded-full transition-[width] duration-200" data-popup-total-bar></div></div>
                    <span class="text-end text-base font-semibold" dir="ltr" data-popup-total>{{ $total }}%</span>
                    <span class="col-span-3 text-sm sm:col-span-1" role="status" aria-live="polite">
                        <span data-total-ok class="inline-flex items-center gap-1.5 text-success" @if ($total !== 100) hidden @endif><x-ui::icon name="check-circle" :size="16" />{{ __('shipping::popup.ready') }}</span>
                        <span data-total-bad class="inline-flex items-center gap-1.5 text-danger" @if ($total === 100) hidden @endif><x-ui::icon name="alert-triangle" :size="16" /><span data-total-bad-text data-template-add="{{ __('shipping::popup.add_more', ['value' => ':value']) }}" data-template-remove="{{ __('shipping::popup.remove_more', ['value' => ':value']) }}"></span></span>
                    </span>
                </div>
                <p data-error-for="days" hidden class="ui-error px-5 pb-4 text-xs text-danger" role="alert"></p>
            </x-ui::card>

            <div class="grid gap-6">
                <x-ui::card :title="__('shipping::popup.preview')" :subtitle="$previewCountry ? __('shipping::popup.preview_help', ['country' => $previewCountry->name, 'date' => now()->locale(app()->getLocale())->isoFormat('ddd D MMM')]) : null">
                    @if ($previewDates === [])
                        <x-ui::empty-state compact icon="globe" :description="__('shipping::popup.preview_empty')" />
                    @else
                        <div class="rounded-control border border-line p-4">
                            <p class="mb-3 flex items-center gap-2 text-sm font-semibold text-ink"><x-ui::icon name="truck" :size="16" class="text-ink-brand" />{{ __('shipping::popup.when') }}</p>
                            <ul class="grid gap-2" role="list">
                                @foreach ($previewDates as $day => $date)
                                    <li class="grid grid-cols-[88px_minmax(0,1fr)_40px] items-center gap-2 text-xs" data-preview-row data-day="{{ $day }}" @if (($percentages[$day] ?? 0) === 0) hidden @endif>
                                        <span class="text-ink-secondary">{{ $date->locale(app()->getLocale())->isoFormat('ddd D MMM') }}</span>
                                        <span class="h-1.5 overflow-hidden rounded-full bg-subtle"><span class="block h-full rounded-full bg-primary" data-preview-bar></span></span>
                                        <span class="text-end font-medium text-ink" dir="ltr" data-preview-value>{{ $percentages[$day] ?? 0 }}%</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </x-ui::card>
                <p class="flex items-start gap-2 rounded-card bg-info-bg px-4 py-3 text-[13px] text-info">
                    <x-ui::icon name="info" :size="16" class="mt-0.5 shrink-0" />
                    <span>{{ __('shipping::popup.validation_help') }}</span>
                </p>
                @if ($customised !== [])
                    <x-ui::card :title="__('shipping::popup.customised')">
                        <ul class="flex flex-wrap gap-2" role="list">
                            @foreach ($customised as $id)
                                <li><a href="{{ route('admin.shipping.delivery-popup.index', ['country' => $id]) }}" class="inline-flex rounded-chip bg-subtle px-2.5 py-1 text-xs font-medium text-ink hover:text-ink-brand">{{ $countries[$id] ?? $id }}</a></li>
                            @endforeach
                        </ul>
                    </x-ui::card>
                @endif
            </div>
        </div>
    </form>
</x-layouts.panel>
