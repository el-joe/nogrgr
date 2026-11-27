{{--
    Vendor profile › Limit overrides (ADM-041h): every plan quota (PlanLimit) with the
    plan value, the override (use plan / custom number / unlimited) and the effective
    value (EffectivePlanLimits). Viewers see it read-only; saving needs
    vendors.limits.manage. The effective column follows the form live (vendors.js).
--}}
@use('App\Modules\Billing\Enums\PlanLimitOverrideMode')
@php
    $canEditLimits = (bool) auth('admin')->user()?->can('vendors.limits.manage');
    $number = fn (int $value) => \Illuminate\Support\Number::format($value, locale: \App\Shared\Support\Locale::numberLocale());
    $hasPlan = (bool) ($limitRows[0]['has_plan'] ?? false);
    $unlimited = __('platform::vendors.limits.unlimited');
    $planText = fn (array $row) => ! $row['has_plan'] ? __('platform::vendors.limits.no_subscription') : ($row['plan'] === null ? $unlimited : $number($row['plan']));
    $modes = PlanLimitOverrideMode::options();
@endphp
<x-ui::card :title="__('platform::vendors.limits.title')" :subtitle="__('platform::vendors.limits.hint')" :padding="false" id="vendor-limits">
    <form
        id="limits-form"
        method="POST"
        action="{{ route('admin.vendors.limits', $tenant->id) }}"
        @if ($canEditLimits) data-ajax @endif
        data-limits-form
        data-unlimited="{{ $unlimited }}"
        novalidate
    >
        @csrf
        @method('PUT')
        @if (! $hasPlan)
            <div class="mx-5 mt-5 flex gap-3 rounded-control bg-info-bg px-4 py-3 text-[13px] text-ink">
                <x-ui::icon name="info" :size="18" class="mt-0.5 shrink-0 text-info" />
                <p>{{ __('platform::vendors.limits.no_plan') }}</p>
            </div>
        @endif
        @if (! $canEditLimits)
            <p class="mx-5 mt-5 text-sm text-ink-secondary">{{ __('platform::vendors.limits.read_only') }}</p>
        @endif
        <div class="relative mt-2 overflow-x-auto">
            <table class="w-full min-w-[720px] text-sm">
                <thead class="bg-subtle text-xs font-medium text-ink-secondary">
                    <tr>
                        <th scope="col" class="px-5 py-3 text-start font-medium">{{ __('platform::vendors.limits.limit') }}</th>
                        <th scope="col" class="px-5 py-3 text-end font-medium">{{ __('platform::vendors.limits.plan_value') }}</th>
                        <th scope="col" class="px-5 py-3 text-start font-medium">{{ __('platform::vendors.limits.override') }}</th>
                        <th scope="col" class="px-5 py-3 text-end font-medium">{{ __('platform::vendors.limits.effective') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($limitRows as $row)
                        @php
                            $key = $row['limit']->value;
                            $isCustom = $row['mode'] === PlanLimitOverrideMode::Custom;
                        @endphp
                        <tr class="border-t border-line align-top" data-limit-row data-plan-text="{{ $planText($row) }}">
                            <th scope="row" class="px-5 py-4 text-start font-medium text-ink">
                                {{ $row['limit']->label() }}
                                @if ($row['mode'] !== PlanLimitOverrideMode::Plan)
                                    <x-ui::badge tone="brand" class="ms-2">{{ __('platform::vendors.limits.overridden') }}</x-ui::badge>
                                @endif
                            </th>
                            <td class="whitespace-nowrap px-5 py-4 text-end text-ink-secondary"><bdi>{{ $planText($row) }}</bdi></td>
                            <td class="px-5 py-3">
                                <div class="grid grid-cols-[180px_160px] gap-3">
                                    <x-ui::select
                                        :name="'limits['.$key.'][mode]'"
                                        :label="__('platform::vendors.limits.mode', ['limit' => $row['limit']->label()])"
                                        label-hidden
                                        :options="$modes"
                                        :selected="$row['mode']->value"
                                        :search="false"
                                        :disabled="! $canEditLimits"
                                        data-limit-mode
                                    />
                                    <div data-limit-value @if (! $isCustom) hidden @endif>
                                        <x-ui::input
                                            :name="'limits['.$key.'][value]'"
                                            type="number"
                                            min="0"
                                            max="100000000"
                                            step="1"
                                            inputmode="numeric"
                                            :label="__('platform::vendors.limits.value', ['limit' => $row['limit']->label()])"
                                            label-hidden
                                            :value="$row['override']"
                                            :disabled="! $canEditLimits"
                                            dir="ltr"
                                        />
                                    </div>
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-5 py-4 text-end font-semibold text-ink" data-limit-effective><bdi>{{ $row['effective'] === null ? ($row['has_plan'] || $row['mode'] === PlanLimitOverrideMode::Unlimited ? $unlimited : __('platform::vendors.limits.no_subscription')) : $number($row['effective']) }}</bdi></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if ($canEditLimits)
            <div class="flex flex-wrap justify-end gap-3 border-t border-line px-5 py-4">
                <x-ui::button variant="secondary" icon="refresh" data-limits-reset>{{ __('platform::vendors.limits.reset') }}</x-ui::button>
                <x-ui::button type="submit" icon="check">{{ __('platform::vendors.limits.save') }}</x-ui::button>
            </div>
        @endif
    </form>
</x-ui::card>
