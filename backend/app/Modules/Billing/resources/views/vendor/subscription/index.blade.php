{{--
    Vendor › Finance › Subscription & balance (VEN-060 / 060c / 060d, Figma Vendor Panel
    88:109601): header actions Change plan / Renew now (VEN-060a modals, Figma 88:113398),
    tabs Overview (balance cards, current plan with usage vs limits, latest ledger
    transactions) · Subscriptions history (subscriptions + invoices tables) · Transactions
    (central ledger of the store). Page module: resources/js/panels/vendor/subscription.js.
--}}
@use('App\Modules\Billing\Enums\SubscriptionPageTab')
@use('App\Modules\Billing\Enums\SubscriptionStatus')
@use('App\Modules\Billing\Enums\BillingTerm')
@use('App\Shared\Money\Money')
@php
    $money = fn (int $minor) => Money::ofMinor($minor)->format();
    $signed = fn (int $minor) => ($minor > 0 ? '+' : '').Money::ofMinor($minor)->format();
    $date = fn ($value) => $value?->locale(app()->getLocale())->translatedFormat('j M Y');
    $tabs = collect(SubscriptionPageTab::cases())->mapWithKeys(fn (SubscriptionPageTab $case) => [
        $case->value => ['label' => $case->label(), 'href' => vendor_route('finance.subscription', $case === SubscriptionPageTab::Overview ? [] : ['tab' => $case->value])],
    ])->all();
    $plan = $subscription?->plan;
    $nextDate = $subscription?->status === SubscriptionStatus::Trial ? $subscription->trial_ends_at : $subscription?->current_period_ends_at;
    $planPayload = $plans->map(fn ($item) => [
        'id' => $item->id,
        'name' => $item->name,
        'prices' => [
            BillingTerm::Monthly->value => ['amount' => $item->priceFor(BillingTerm::Monthly)->format(), 'payable' => $item->priceFor(BillingTerm::Monthly)->isPositive()],
            BillingTerm::Yearly->value => ['amount' => $item->priceFor(BillingTerm::Yearly)->format(), 'payable' => $item->priceFor(BillingTerm::Yearly)->isPositive()],
        ],
    ])->values();
    $defaultPlan = $plans->firstWhere('id', $plan?->id) ?? $plans->first();
    $defaultTerm = $subscription?->term ?? BillingTerm::Monthly;
    $termLabels = collect(BillingTerm::cases())->mapWithKeys(fn (BillingTerm $term) => [$term->value => $term->label()])->all();
@endphp
<x-vendor::layout :title="__('billing::vendor_subscription.title')" page="vendor-subscription">
    <div class="grid gap-6">
        <x-ui::page-header :title="__('billing::vendor_subscription.title')" :subtitle="__('billing::vendor_subscription.subtitle')"
            :breadcrumbs="[__('billing::vendor_subscription.crumb') => null, __('billing::vendor_subscription.title') => null]">
            @if ($canManage)
                <x-slot:actions>
                    @if ($plans->isNotEmpty())
                        <x-ui::button variant="secondary" icon="star" data-overlay-open="change-plan-modal">{{ $subscription ? __('billing::vendor_subscription.actions.change_plan') : __('billing::vendor_subscription.actions.choose_plan') }}</x-ui::button>
                    @endif
                    @if ($renewable)
                        <x-ui::button icon="credit-card" data-overlay-open="renew-modal">{{ __('billing::vendor_subscription.actions.renew_now') }}</x-ui::button>
                    @endif
                </x-slot:actions>
            @endif
            <x-slot:tabs>
                <x-ui::tabs id="subscription-tabs" :active="$tab->value" :label="__('billing::vendor_subscription.title')" :items="$tabs" />
            </x-slot:tabs>
        </x-ui::page-header>

        @if (session('error'))
            <p class="flex items-start gap-2 rounded-control bg-danger-bg px-4 py-3 text-sm text-danger" role="alert"><x-ui::icon name="alert-triangle" :size="18" class="mt-0.5 shrink-0" />{{ session('error') }}</p>
        @endif
        @if ($errors->any())
            <p class="flex items-start gap-2 rounded-control bg-danger-bg px-4 py-3 text-sm text-danger" role="alert"><x-ui::icon name="alert-triangle" :size="18" class="mt-0.5 shrink-0" />{{ $errors->first() }}</p>
        @endif

        @if ($unpaidInvoice)
            <div class="flex flex-wrap items-center gap-3 rounded-card bg-warning-bg px-4 py-3 text-sm text-ink" role="alert" data-unpaid-invoice>
                <x-ui::icon name="alert-triangle" :size="18" class="shrink-0 text-warning" />
                <p class="min-w-0 flex-1">{{ __('billing::vendor_subscription.past_due', ['number' => $unpaidInvoice->number, 'amount' => $unpaidInvoice->total()->format()]) }}</p>
                @if ($canManage)
                    <x-ui::button size="sm" icon="credit-card"
                        data-invoice-pay
                        :data-invoice-id="$unpaidInvoice->id"
                        :data-invoice-number="$unpaidInvoice->number"
                        :data-invoice-amount="$unpaidInvoice->total()->format()"
                    >{{ __('billing::vendor_subscription.actions.pay_now') }}</x-ui::button>
                @endif
            </div>
        @endif

        @if ($tab === SubscriptionPageTab::Overview)
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <x-ui::kpi-card icon="wallet" :label="__('billing::vendor_subscription.kpi.balance')" :value="$money($balance['net'])" :comparison="__('billing::vendor_subscription.kpi.balance_hint')" />
                <x-ui::kpi-card icon="alert-triangle" :label="__('billing::vendor_subscription.kpi.owed')" :value="$money($balance['owed'])" :comparison="__('billing::vendor_subscription.kpi.owed_hint')" />
                <x-ui::kpi-card icon="calendar" :label="__('billing::vendor_subscription.kpi.payout')" :value="$money($balance['open_payouts'])" :comparison="__('billing::vendor_subscription.kpi.payout_hint')" />
                <x-ui::kpi-card icon="star" :label="__('billing::vendor_subscription.kpi.renews')"
                    :value="$nextDate ? $date($nextDate) : __('billing::vendor_subscription.kpi.renews_none')"
                    :comparison="$plan ? __('billing::vendor_subscription.kpi.renews_hint', ['plan' => $plan->name, 'term' => $subscription->term->label()]) : null" />
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <x-ui::card id="current-plan" :title="__('billing::vendor_subscription.plan.title')">
                    @if ($subscription)
                        <x-slot:actions>
                            @include('billing::admin._status', ['status' => $subscription->status])
                        </x-slot:actions>
                        <dl class="grid gap-3 text-sm">
                            <div class="flex justify-between gap-4">
                                <dt class="text-ink-secondary">{{ __('billing::vendor_subscription.plan.plan') }}</dt>
                                <dd class="text-end font-medium text-ink">{{ __('billing::vendor_subscription.plan.summary', ['plan' => (string) $plan?->name, 'term' => $subscription->term->label(), 'amount' => $subscription->amount()->format()]) }}</dd>
                            </div>
                            <div class="flex justify-between gap-4">
                                <dt class="text-ink-secondary">{{ $subscription->status === SubscriptionStatus::Trial ? __('billing::vendor_subscription.plan.trial_ends') : ($subscription->auto_renew ? __('billing::vendor_subscription.plan.renews') : __('billing::vendor_subscription.plan.ends')) }}</dt>
                                <dd class="text-end font-medium text-ink"><bdi dir="ltr">{{ $date($nextDate) ?? '—' }}</bdi> · {{ $subscription->auto_renew ? __('billing::vendor_subscription.plan.auto') : __('billing::vendor_subscription.plan.manual') }}</dd>
                            </div>
                            @if ($subscription->gateway)
                                <div class="flex justify-between gap-4">
                                    <dt class="text-ink-secondary">{{ __('billing::vendor_subscription.plan.gateway') }}</dt>
                                    <dd class="text-end font-medium text-ink">{{ $subscription->gateway->label() }}</dd>
                                </div>
                            @endif
                        </dl>
                        <div class="mt-5 grid gap-4">
                            @foreach ($usage as $row)
                                <div class="grid gap-2" data-usage="{{ $row['limit']->value }}">
                                    <div class="flex justify-between gap-4 text-sm">
                                        <span class="font-medium text-ink">{{ $row['limit']->label() }}</span>
                                        <span class="text-ink-secondary" dir="ltr">{{ $row['max'] === null ? number_format($row['used']).' / '.__('billing::vendor_subscription.plan.unlimited') : __('billing::vendor_subscription.plan.usage_of', ['used' => number_format($row['used']), 'max' => number_format($row['max'])]) }}</span>
                                    </div>
                                    <progress class="h-2 w-full appearance-none overflow-hidden rounded-full bg-subtle [&::-moz-progress-bar]:rounded-full [&::-moz-progress-bar]:bg-primary [&::-webkit-progress-bar]:bg-subtle [&::-webkit-progress-value]:rounded-full [&::-webkit-progress-value]:bg-primary [&.is-full::-moz-progress-bar]:bg-danger [&.is-full::-webkit-progress-value]:bg-danger" max="100" value="{{ $row['max'] === null ? 0 : $row['percent'] }}" aria-label="{{ __('billing::vendor_subscription.plan.usage_label', ['label' => $row['limit']->label()]) }}" @class(['is-full' => $row['percent'] >= 100])></progress>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <x-ui::empty-state compact icon="star" :title="__('billing::vendor_subscription.plan.empty_title')" :description="__('billing::vendor_subscription.plan.empty_description')">
                            @if ($canManage && $plans->isNotEmpty())
                                <x-ui::button icon="star" data-overlay-open="change-plan-modal">{{ __('billing::vendor_subscription.actions.choose_plan') }}</x-ui::button>
                            @endif
                        </x-ui::empty-state>
                    @endif
                </x-ui::card>

                <x-ui::card id="recent-transactions" :title="__('billing::vendor_subscription.recent.title')" :padding="false">
                    @if ($recent->isNotEmpty())
                        <x-slot:actions>
                            <a href="{{ vendor_route('finance.subscription', ['tab' => 'transactions']) }}" class="text-sm font-medium text-ink-brand hover:underline">{{ __('billing::vendor_subscription.actions.view_all') }}</a>
                        </x-slot:actions>
                        <div class="overflow-x-auto">
                            <table class="vnd-table w-full text-sm">
                                <thead class="bg-subtle text-xs text-ink-secondary">
                                    <tr>
                                        <th scope="col" class="px-5 py-3 text-start font-normal">{{ __('billing::vendor_subscription.recent.date') }}</th>
                                        <th scope="col" class="px-5 py-3 text-start font-normal">{{ __('billing::vendor_subscription.recent.description') }}</th>
                                        <th scope="col" class="px-5 py-3 text-end font-normal">{{ __('billing::vendor_subscription.recent.amount') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-line">
                                    @foreach ($recent as $transaction)
                                        <tr>
                                            <td class="whitespace-nowrap h-12 px-5 py-3 text-ink-muted" dir="ltr">{{ $transaction->occurred_at->locale(app()->getLocale())->translatedFormat('j M') }}</td>
                                            <td class="h-12 px-5 py-3 text-ink-muted">{{ \App\Modules\Billing\Support\LedgerPresenter::title($transaction) }}</td>
                                            <td class="whitespace-nowrap h-12 px-5 py-3 text-end text-ink-muted" dir="ltr">{{ $signed($transaction->net_amount) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="px-5 pb-5">
                            <x-ui::empty-state compact icon="wallet" :title="__('billing::vendor_subscription.recent.empty_title')" :description="__('billing::vendor_subscription.recent.empty_description')" />
                        </div>
                    @endif
                </x-ui::card>
            </div>
        @elseif ($tab === SubscriptionPageTab::History)
            <x-ui::datatable
                id="vendor-subscriptions"
                :url="vendor_route('finance.subscription.history.data')"
                :columns="[
                    ['data' => 'plan', 'title' => __('billing::vendor_subscription.history.columns.plan'), 'orderable' => false, 'searchable' => false],
                    ['data' => 'term', 'title' => __('billing::vendor_subscription.history.columns.billing'), 'searchable' => false],
                    ['data' => 'period', 'name' => 'current_period_starts_at', 'title' => __('billing::vendor_subscription.history.columns.period'), 'searchable' => false],
                    ['data' => 'amount_minor', 'title' => __('billing::vendor_subscription.history.columns.amount'), 'align' => 'end', 'searchable' => false],
                    ['data' => 'status', 'title' => __('billing::vendor_subscription.history.columns.status'), 'searchable' => false],
                    ['data' => 'invoice', 'title' => __('billing::vendor_subscription.history.columns.invoice'), 'orderable' => false, 'searchable' => false],
                ]"
                :order="[[2, 'desc']]"
                :empty-title="__('billing::vendor_subscription.history.empty_subscriptions_title')"
                :empty-description="__('billing::vendor_subscription.history.empty_subscriptions')"
            >
                <x-slot:filters>
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h2 class="font-semibold text-ink">{{ __('billing::vendor_subscription.history.subscriptions_title') }}</h2>
                        <x-ui::filters-bar target="vendor-subscriptions" :search-placeholder="__('billing::vendor_subscription.history.search_invoice')">
                            <x-ui::select name="status" :label="__('billing::vendor_subscription.history.columns.status')" label-hidden :options="$subscriptionStatuses" :placeholder="__('billing::vendor_subscription.history.columns.status')" :search="false" allow-clear data-filter :data-filter-label="__('billing::vendor_subscription.history.columns.status')" class="w-40" />
                        </x-ui::filters-bar>
                    </div>
                </x-slot:filters>
            </x-ui::datatable>

            <x-ui::datatable
                id="vendor-invoices"
                :url="vendor_route('finance.subscription.invoices.data')"
                :columns="[
                    ['data' => 'number', 'title' => __('billing::vendor_subscription.history.columns.number'), 'searchable' => false],
                    ['data' => 'type', 'title' => __('billing::vendor_subscription.history.columns.type'), 'searchable' => false],
                    ['data' => 'issued_at', 'title' => __('billing::vendor_subscription.history.columns.issued'), 'searchable' => false],
                    ['data' => 'total_minor', 'title' => __('billing::vendor_subscription.history.columns.total'), 'align' => 'end', 'searchable' => false],
                    ['data' => 'status', 'title' => __('billing::vendor_subscription.history.columns.status'), 'searchable' => false],
                    ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
                ]"
                :order="[[2, 'desc']]"
                :empty-title="__('billing::vendor_subscription.history.empty_invoices_title')"
                :empty-description="__('billing::vendor_subscription.history.empty_invoices')"
            >
                <x-slot:filters>
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h2 class="font-semibold text-ink">{{ __('billing::vendor_subscription.history.invoices_title') }}</h2>
                        <x-ui::filters-bar target="vendor-invoices" :search-placeholder="__('billing::vendor_subscription.history.search_invoice')">
                            <x-ui::select name="status" :label="__('billing::vendor_subscription.history.columns.status')" label-hidden :options="$invoiceStatuses" :placeholder="__('billing::vendor_subscription.history.columns.status')" :search="false" allow-clear data-filter :data-filter-label="__('billing::vendor_subscription.history.columns.status')" class="w-40" />
                        </x-ui::filters-bar>
                    </div>
                </x-slot:filters>
            </x-ui::datatable>
        @else
            <x-ui::datatable
                id="vendor-transactions"
                :url="vendor_route('finance.subscription.transactions.data')"
                :columns="[
                    ['data' => 'date', 'title' => __('billing::finance.ledger.entries.date')],
                    ['data' => 'entry', 'title' => __('billing::finance.ledger.entries.entry'), 'orderable' => false],
                    ['data' => 'debit', 'title' => __('billing::finance.ledger.entries.debit'), 'align' => 'end', 'orderable' => false],
                    ['data' => 'credit', 'title' => __('billing::finance.ledger.entries.credit'), 'align' => 'end', 'orderable' => false],
                    ['data' => 'balance', 'title' => __('billing::finance.ledger.entries.balance'), 'align' => 'end'],
                ]"
                :order="[[0, 'desc']]"
                :empty-title="__('billing::vendor_subscription.recent.empty_title')"
                :empty-description="__('billing::vendor_subscription.recent.empty_description')"
            >
                <x-slot:filters>
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h2 class="font-semibold text-ink">{{ __('billing::vendor_subscription.transactions.title') }}</h2>
                        <x-ui::filters-bar target="vendor-transactions" :search-placeholder="__('billing::finance.transactions.search')">
                            <x-ui::select name="source" :label="__('billing::finance.transactions.filters.source')" label-hidden :options="\App\Modules\Billing\Enums\LedgerSource::options()" :placeholder="__('billing::finance.transactions.filters.source')" :search="false" allow-clear data-filter :data-filter-label="__('billing::finance.transactions.filters.source')" class="w-40" />
                        </x-ui::filters-bar>
                    </div>
                </x-slot:filters>
            </x-ui::datatable>
        @endif
    </div>

    @if ($canManage)
        {{-- VEN-060a Change plan (Figma 88:113398) --}}
        <x-ui::modal id="change-plan-modal" :title="__('billing::vendor_subscription.change.title')" :subtitle="__('billing::vendor_subscription.change.subtitle')">
            @if ($plans->isEmpty())
                <x-ui::empty-state compact variant="error" icon="star" :title="__('billing::vendor_subscription.change.no_plans_title')" :description="__('billing::vendor_subscription.change.no_plans')" />
            @elseif ($gateways->isEmpty())
                <x-ui::empty-state compact variant="error" icon="credit-card" :title="__('billing::vendor_subscription.no_gateways_title')" :description="__('billing::vendor_subscription.no_gateways')" />
            @else
                <script type="application/json" data-plan-prices>@json(['plans' => $planPayload, 'terms' => $termLabels])</script>
                <form id="change-plan-form" method="POST" action="{{ vendor_route('finance.subscription.checkout') }}" class="grid gap-5" data-change-plan-form novalidate>
                    @csrf
                    <input type="hidden" name="action" value="change">
                    <fieldset class="grid gap-3">
                        <legend class="mb-2 text-sm font-medium text-ink">{{ __('billing::vendor_subscription.change.plan') }}</legend>
                        @foreach ($plans as $option)
                            <x-ui::radio name="plan_id" :id="'change-plan-'.$option->id" :value="$option->id" :checked="$option->id === $defaultPlan?->id"
                                :label="$option->id === $plan?->id ? $option->name.' · '.__('billing::vendor_subscription.change.current') : $option->name" />
                        @endforeach
                    </fieldset>
                    <fieldset class="grid gap-3">
                        <legend class="mb-2 text-sm font-medium text-ink">{{ __('billing::vendor_subscription.change.billing') }}</legend>
                        <div class="flex flex-wrap gap-6">
                            @foreach (BillingTerm::cases() as $term)
                                <x-ui::radio name="term" :id="'change-term-'.$term->value" :value="$term->value" :label="$term->label()" :checked="$term === $defaultTerm" />
                            @endforeach
                        </div>
                    </fieldset>
                    <dl class="grid grid-cols-[1fr_auto] gap-x-6 gap-y-2 text-sm">
                        <dt class="text-ink-secondary" data-change-line>{{ $defaultPlan ? __('billing::vendor_subscription.change.line', ['plan' => $defaultPlan->name, 'term' => mb_strtolower($defaultTerm->label())]) : '' }}</dt>
                        <dd class="text-end font-medium text-ink"><bdi dir="ltr" data-change-price>{{ $defaultPlan?->priceFor($defaultTerm)->format() }}</bdi></dd>
                        <dt class="font-medium text-ink">{{ __('billing::vendor_subscription.change.due_today') }}</dt>
                        <dd class="text-end font-semibold text-ink"><bdi dir="ltr" data-change-due>{{ $defaultPlan?->priceFor($defaultTerm)->format() }}</bdi></dd>
                    </dl>
                    @include('billing::vendor.subscription._gateways', ['gateways' => $gateways, 'prefix' => 'change'])
                </form>
            @endif
            <x-slot:footer>
                <x-ui::button variant="secondary" data-overlay-close>{{ __('ui.cancel') }}</x-ui::button>
                @if ($plans->isNotEmpty() && $gateways->isNotEmpty())
                    <x-ui::button type="submit" form="change-plan-form" icon="credit-card" data-change-submit :data-label-template="__('billing::vendor_subscription.actions.pay_amount', ['amount' => '__AMOUNT__'])">{{ __('billing::vendor_subscription.actions.pay_amount', ['amount' => $defaultPlan?->priceFor($defaultTerm)->format()]) }}</x-ui::button>
                @endif
            </x-slot:footer>
        </x-ui::modal>

        @if ($renewable)
            <x-ui::modal id="renew-modal" :title="__('billing::vendor_subscription.renew.title')" :subtitle="__('billing::vendor_subscription.renew.subtitle')" size="sm">
                @if ($gateways->isEmpty())
                    <x-ui::empty-state compact variant="error" icon="credit-card" :title="__('billing::vendor_subscription.no_gateways_title')" :description="__('billing::vendor_subscription.no_gateways')" />
                @else
                    <form id="renew-form" method="POST" action="{{ vendor_route('finance.subscription.checkout') }}" class="grid gap-5" data-checkout-form novalidate>
                        @csrf
                        <input type="hidden" name="action" value="renew">
                        <dl class="grid grid-cols-[auto_1fr] gap-x-6 gap-y-2 rounded-control bg-subtle px-4 py-3 text-sm">
                            <dt class="text-ink-secondary">{{ __('billing::vendor_subscription.plan.plan') }}</dt>
                            <dd class="text-end text-ink">{{ $plan?->name }} · {{ $subscription->term->label() }}</dd>
                            <dt class="text-ink-secondary">{{ __('billing::vendor_subscription.change.due_today') }}</dt>
                            <dd class="text-end font-semibold text-ink"><bdi dir="ltr">{{ $subscription->amount()->format() }}</bdi></dd>
                        </dl>
                        @include('billing::vendor.subscription._gateways', ['gateways' => $gateways, 'prefix' => 'renew'])
                    </form>
                @endif
                <x-slot:footer>
                    <x-ui::button variant="secondary" data-overlay-close>{{ __('ui.cancel') }}</x-ui::button>
                    @if ($gateways->isNotEmpty())
                        <x-ui::button type="submit" form="renew-form" icon="credit-card">{{ __('billing::vendor_subscription.actions.pay_amount', ['amount' => $subscription->amount()->format()]) }}</x-ui::button>
                    @endif
                </x-slot:footer>
            </x-ui::modal>
        @endif

        <x-ui::modal id="invoice-pay-modal" :title="__('billing::vendor_subscription.invoice.title')" :subtitle="__('billing::vendor_subscription.invoice.subtitle')" size="sm">
            @if ($invoiceGateways->isEmpty())
                <x-ui::empty-state compact variant="error" icon="credit-card" :title="__('billing::vendor_subscription.no_gateways_title')" :description="__('billing::vendor_subscription.no_gateways')" />
            @else
                <form id="invoice-pay-form" method="POST" action="{{ vendor_route('finance.subscription.checkout') }}" class="grid gap-5" data-checkout-form novalidate>
                    @csrf
                    <input type="hidden" name="action" value="invoice">
                    <input type="hidden" name="invoice_id" value="{{ $unpaidInvoice?->id }}" data-invoice-id-input>
                    <dl class="grid grid-cols-[auto_1fr] gap-x-6 gap-y-2 rounded-control bg-subtle px-4 py-3 text-sm">
                        <dt class="text-ink-secondary">{{ __('billing::vendor_subscription.invoice.number') }}</dt>
                        <dd class="text-end font-medium text-ink" dir="ltr" data-invoice-number-label>{{ $unpaidInvoice?->number }}</dd>
                        <dt class="text-ink-secondary">{{ __('billing::vendor_subscription.invoice.amount') }}</dt>
                        <dd class="text-end font-semibold text-ink"><bdi dir="ltr" data-invoice-amount-label>{{ $unpaidInvoice?->total()->format() }}</bdi></dd>
                    </dl>
                    @include('billing::vendor.subscription._gateways', ['gateways' => $invoiceGateways, 'prefix' => 'invoice'])
                </form>
            @endif
            <x-slot:footer>
                <x-ui::button variant="secondary" data-overlay-close>{{ __('ui.cancel') }}</x-ui::button>
                @if ($invoiceGateways->isNotEmpty())
                    <x-ui::button type="submit" form="invoice-pay-form" icon="credit-card">{{ __('billing::vendor_subscription.continue_payment') }}</x-ui::button>
                @endif
            </x-slot:footer>
        </x-ui::modal>
    @endif
</x-vendor::layout>
