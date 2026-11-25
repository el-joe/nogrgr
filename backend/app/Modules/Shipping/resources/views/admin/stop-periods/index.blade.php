{{--
    Admin › Logistics › Shipping › Stop periods (ADM-033c, Figma Admin Panel 71:47208): month
    calendar (stop days per company / all companies), periods list, company + country filter,
    add / edit (modal) and delete. Days here are skipped by the delivery estimator.
--}}
@php
    $canManage = (bool) auth('admin')->user()?->can('shipping.stop-periods.manage');
    $query = array_filter(['company' => $companyId, 'country' => $countryId]);
    $today = now()->toDateString();
@endphp
<x-layouts.panel surface="admin" :title="__('shipping::stop_periods.title')" data-page="admin-shipping">
    <div class="grid gap-6">
        <x-ui::page-header
            :title="__('shipping::stop_periods.title')"
            :subtitle="__('shipping::stop_periods.subtitle')"
            :breadcrumbs="[__('navigation.sections.logistics') => null, __('navigation.admin.shipping') => null, __('shipping::stop_periods.title') => null]"
        >
            @if ($canManage)
                <x-slot:actions>
                    <x-ui::button icon="plus" data-remote-modal="stop-period-modal" :data-url="route('admin.shipping.stop-periods.create', array_filter(['company' => $companyId]))">{{ __('shipping::stop_periods.add') }}</x-ui::button>
                </x-slot:actions>
            @endif
        </x-ui::page-header>

        <div class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
            <x-ui::card>
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <x-ui::icon-button icon="chevron-left" size="sm" class="rtl:rotate-180" :label="__('shipping::stop_periods.previous_month')" :href="route('admin.shipping.stop-periods.index', [...$query, 'month' => $month->subMonth()->format('Y-m')])" />
                        <h2 class="min-w-36 text-center text-base font-semibold text-ink">{{ $month->locale(app()->getLocale())->isoFormat('MMMM YYYY') }}</h2>
                        <x-ui::icon-button icon="chevron-right" size="sm" class="rtl:rotate-180" :label="__('shipping::stop_periods.next_month')" :href="route('admin.shipping.stop-periods.index', [...$query, 'month' => $month->addMonth()->format('Y-m')])" />
                    </div>
                    <div class="flex items-center gap-4 text-xs text-ink-secondary">
                        <span class="inline-flex items-center gap-1.5"><span class="size-2 rounded-full bg-danger"></span>{{ __('shipping::stop_periods.legend_company') }}</span>
                        <span class="inline-flex items-center gap-1.5"><span class="size-2 rounded-full bg-warning"></span>{{ __('shipping::stop_periods.all_companies') }}</span>
                    </div>
                </div>
                <div class="relative overflow-x-auto">
                    <table class="w-full min-w-[640px] table-fixed border-separate border-spacing-1 text-sm" aria-label="{{ $month->locale(app()->getLocale())->isoFormat('MMMM YYYY') }}">
                        <thead>
                            <tr>
                                @foreach (\App\Modules\Shipping\Enums\Weekday::cases() as $weekday)
                                    <th scope="col" class="pb-1 text-center text-xs font-medium text-ink-secondary">{{ \Illuminate\Support\Str::limit($weekday->label(), 3, '') }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($weeks as $week)
                                <tr>
                                    @foreach ($week as $cell)
                                        @if ($cell['date'] === null)
                                            <td class="h-20 rounded-control bg-subtle"></td>
                                        @else
                                            <td @class(['h-20 rounded-control border p-1.5 align-top', 'border-primary' => $cell['date']->toDateString() === $today, 'border-line' => $cell['date']->toDateString() !== $today])>
                                                <span class="text-xs font-medium text-ink" dir="ltr">{{ $cell['date']->day }}</span>
                                                <div class="mt-1 grid gap-0.5">
                                                    @foreach (array_slice($cell['periods'], 0, 2) as $period)
                                                        <span @class(['truncate rounded px-1 text-[11px] leading-4', 'bg-danger-bg text-danger' => $period->shipping_company_id, 'bg-warning-bg text-warning' => ! $period->shipping_company_id]) title="{{ $period->name }}">{{ $period->name }}</span>
                                                    @endforeach
                                                    @if (count($cell['periods']) > 2)
                                                        <span class="text-[11px] text-ink-secondary">+{{ count($cell['periods']) - 2 }}</span>
                                                    @endif
                                                </div>
                                            </td>
                                        @endif
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-ui::card>

            <div class="grid gap-6">
                <x-ui::card :title="__('shipping::stop_periods.periods')">
                    @include('shipping::admin.stop-periods._list', ['periods' => $periods, 'canManage' => $canManage])
                </x-ui::card>
                <x-ui::card :title="__('shipping::stop_periods.filter')">
                    <form method="GET" action="{{ route('admin.shipping.stop-periods.index') }}" class="grid gap-4" data-auto-submit>
                        <input type="hidden" name="month" value="{{ $month->format('Y-m') }}">
                        <x-ui::select name="company" :label="__('shipping::stop_periods.fields.company')" :options="$companies" :selected="$companyId" :placeholder="__('shipping::stop_periods.all_companies')" allow-clear />
                        <x-ui::select name="country" :label="__('shipping::stop_periods.fields.country')" :options="$countries" :selected="$countryId" :placeholder="__('shipping::stop_periods.all_countries')" allow-clear />
                        <x-ui::button type="submit" variant="secondary" icon="filter">{{ __('shipping::stop_periods.apply') }}</x-ui::button>
                    </form>
                </x-ui::card>
            </div>
        </div>
    </div>

    @if ($canManage)
        @include('shipping::admin.stop-periods._modal')
    @endif
</x-layouts.panel>
