{{--
    Admin › Integrations › Gateway limits (ADM-100c, Figma Admin Panel 79:86634, decision
    #124): per gateway the monthly volume each store may take through the PLATFORM account
    (warning at the set %, block at 100 %: the gateway leaves that store's checkout until
    next month or an unblock), and this month's usage per store (server-side DataTable).
--}}
<x-layouts.panel surface="admin" :title="__('integrations::admin.tabs.limits').' · '.__('integrations::admin.title')" data-page="admin-integrations">
    @include('integrations::admin.integrations._header', ['active' => 'limits'])

    <div class="grid gap-6">
        <form id="gateway-limits" method="POST" action="{{ route('admin.integrations.gateways.limits.update') }}" data-ajax data-unsaved-form novalidate>
            @csrf
            @method('PUT')
            <x-ui::card :padding="false">
                <div class="flex flex-wrap items-start justify-between gap-3 border-b border-line px-5 py-4">
                    <div>
                        <h2 class="text-base font-semibold text-ink">{{ __('integrations::admin.limits.title') }}</h2>
                        <p class="text-[13px] text-ink-secondary">{{ __('integrations::admin.limits.help') }}</p>
                    </div>
                    @if ($canManage)
                        <x-ui::button type="submit" icon="check">{{ __('integrations::admin.save') }}</x-ui::button>
                    @endif
                </div>
                @if ($gateways->isEmpty())
                    <x-ui::empty-state :title="__('integrations::admin.gateways.empty_title')" :description="__('integrations::admin.gateways.empty_description')" />
                @else
                    <div class="divide-y divide-line">
                        @foreach ($gateways as $gateway)
                            @php
                                $code = $gateway->code->value;
                                $limit = $gateway->monthly_store_limit_minor;
                            @endphp
                            <div class="grid gap-4 px-5 py-4 sm:grid-cols-[minmax(0,1fr)_200px_160px] sm:items-end">
                                <div class="min-w-0">
                                    <p class="font-medium text-ink">{{ $gateway->code->brand() }}</p>
                                    <p class="text-xs text-ink-secondary">{{ $limit ? __('integrations::admin.limits.summary', ['limit' => \App\Shared\Money\Money::ofMinor($limit)->format(), 'percent' => \App\Modules\Integrations\Services\Limits\GatewayLimitService::warnPercent($gateway)]) : __('integrations::admin.limits.no_limit_help') }}</p>
                                </div>
                                <x-ui::input :name="'limits['.$code.'][limit]'" :id="'limit-'.$code" type="number" min="0" step="0.01" :label="__('integrations::admin.limits.fields.limit')" :value="$limit ? number_format($limit / 100, 2, '.', '') : ''" :placeholder="__('integrations::admin.limits.no_limit')" :disabled="! $canManage" dir="ltr" inputmode="decimal" />
                                <x-ui::input :name="'limits['.$code.'][warn_percent]'" :id="'warn-'.$code" type="number" min="1" max="99" :label="__('integrations::admin.limits.fields.warn_percent')" :value="\App\Modules\Integrations\Services\Limits\GatewayLimitService::warnPercent($gateway)" :disabled="! $canManage" dir="ltr" inputmode="numeric" />
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-ui::card>
        </form>

        <div class="grid gap-3">
            <div>
                <h2 class="text-base font-semibold text-ink">{{ __('integrations::admin.limits.usage_title', ['period' => $period]) }}</h2>
                <p class="text-[13px] text-ink-secondary">{{ __('integrations::admin.limits.usage_help') }}</p>
            </div>
            <x-ui::datatable
                id="gateway-limit-usages"
                :url="route('admin.integrations.gateways.limits.data')"
                :columns="[
                    ['data' => 'store', 'title' => __('integrations::admin.limits.columns.store'), 'orderable' => false, 'searchable' => false],
                    ['data' => 'gateway', 'title' => __('integrations::admin.limits.columns.gateway'), 'searchable' => false],
                    ['data' => 'volume_minor', 'title' => __('integrations::admin.limits.columns.volume'), 'align' => 'end', 'searchable' => false],
                    ['data' => 'limit', 'title' => __('integrations::admin.limits.columns.limit'), 'align' => 'end', 'orderable' => false, 'searchable' => false],
                    ['data' => 'percent', 'title' => __('integrations::admin.limits.columns.percent'), 'align' => 'end', 'orderable' => false, 'searchable' => false],
                    ['data' => 'state', 'title' => __('integrations::admin.limits.columns.state'), 'orderable' => false, 'searchable' => false],
                    ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
                ]"
                :order="[[2, 'desc']]"
                :empty-title="__('integrations::admin.limits.empty_title')"
                :empty-description="__('integrations::admin.limits.empty_description')"
            >
                <x-slot:filters>
                    <x-ui::filters-bar target="gateway-limit-usages" :search-placeholder="__('integrations::admin.limits.search')">
                        <x-ui::select name="gateway" :label="__('integrations::admin.limits.columns.gateway')" label-hidden :options="$gatewayOptions" :placeholder="__('integrations::admin.limits.columns.gateway')" :search="false" allow-clear data-filter :data-filter-label="__('integrations::admin.limits.columns.gateway')" class="w-40" />
                        <x-ui::select name="state" :label="__('integrations::admin.limits.columns.state')" label-hidden :options="['warned' => __('integrations::admin.limits.states.warned'), 'blocked' => __('integrations::admin.limits.states.blocked')]" :placeholder="__('integrations::admin.limits.columns.state')" :search="false" allow-clear data-filter :data-filter-label="__('integrations::admin.limits.columns.state')" class="w-40" />
                    </x-ui::filters-bar>
                </x-slot:filters>
            </x-ui::datatable>
        </div>
    </div>
</x-layouts.panel>
