{{-- Stop periods list (calendar sidebar + company tab): name, when, scope, actions. --}}
@if ($periods->isEmpty())
    <x-ui::empty-state compact icon="calendar" :title="__('shipping::stop_periods.empty_title')" :description="__('shipping::stop_periods.empty_description')" />
@else
    <ul class="divide-y divide-line" role="list">
        @foreach ($periods as $period)
            <li class="flex items-start gap-3 py-3">
                <span class="grid size-9 shrink-0 place-items-center rounded-control bg-subtle text-ink-secondary"><x-ui::icon name="calendar" :size="16" /></span>
                <div class="grid min-w-0 flex-1 gap-0.5">
                    <span class="truncate text-sm font-medium text-ink">{{ $period->name }}</span>
                    <span class="text-xs text-ink-secondary">
                        @if ($period->recurrence === \App\Modules\Shipping\Enums\StopPeriodRecurrence::Weekly)
                            {{ __('shipping::stop_periods.every', ['day' => $period->weekday?->label()]) }}
                        @elseif ($period->ends_on && ! $period->ends_on->isSameDay($period->starts_on))
                            {{ $period->starts_on->translatedFormat('j M Y') }} – {{ $period->ends_on->translatedFormat('j M Y') }}
                        @else
                            {{ $period->starts_on->translatedFormat('j M Y') }}
                        @endif
                        · {{ $period->country?->name ?? __('shipping::stop_periods.all_countries') }}
                    </span>
                </div>
                <x-ui::status :status="$period->is_active ? ($period->shipping_company_id ? 'cancelled' : 'pending') : 'inactive'">{{ $period->company?->name ?? __('shipping::stop_periods.all_companies_short') }}</x-ui::status>
                @if ($canManage)
                    <x-ui::row-actions>
                        <x-ui::dropdown-item icon="edit" data-remote-modal="stop-period-modal" :data-url="route('admin.shipping.stop-periods.edit', $period)">{{ __('shipping::common.edit') }}</x-ui::dropdown-item>
                        <x-ui::dropdown-divider />
                        <x-ui::dropdown-item icon="trash" tone="danger" :data-confirm="__('shipping::stop_periods.delete_message')" :data-confirm-title="__('shipping::stop_periods.delete_title', ['name' => $period->name])" data-confirm-method="DELETE" :data-confirm-url="route('admin.shipping.stop-periods.destroy', $period)">{{ __('shipping::common.delete') }}</x-ui::dropdown-item>
                    </x-ui::row-actions>
                @endif
            </li>
        @endforeach
    </ul>
@endif
