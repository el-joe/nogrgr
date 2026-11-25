{{--
    Vendor profile › Plan & subscription (ADM-041b, Figma 72:50656): current plan card
    and subscription history (invoice links when allowed). "Assign plan" opens the
    Billing assign form with the store fixed. Usage vs limits is not shown (no usage data yet).
--}}
@php
    $canAssign = (bool) auth('admin')->user()?->can('subscriptions.manage') && \Illuminate\Support\Facades\Route::has('admin.billing.subscriptions.assign');
    $canInvoices = (bool) auth('admin')->user()?->can('finance.invoices.view') && \Illuminate\Support\Facades\Route::has('admin.finance.invoices.show');
    $isTrial = $subscription && $subscription->status === \App\Modules\Billing\Enums\SubscriptionStatus::Trial;
@endphp
<div class="grid items-start gap-6">
    <x-ui::card :title="__('platform::vendors.plan.current')" id="current-plan">
        @if ($subscription || $canAssign)
            <x-slot:actions>
                <div class="flex items-center gap-3">
                    @if ($subscription)
                        <x-ui::badge :tone="$subscription->status->tone()">{{ $subscription->status->label() }}</x-ui::badge>
                    @endif
                    @if ($canAssign)
                        <x-ui::button size="sm" icon="star" data-overlay-open="assign-plan">{{ __('platform::vendors.plan.assign') }}</x-ui::button>
                    @endif
                </div>
            </x-slot:actions>
        @endif
        @if ($subscription)
            <dl class="grid gap-3 text-sm">
                <div class="flex items-start justify-between gap-4">
                    <dt class="text-ink-secondary">{{ __('platform::vendors.plan.plan') }}</dt>
                    <dd class="text-end font-medium text-ink">{{ $subscription->plan->name }} · {{ $subscription->term->label() }}</dd>
                </div>
                <div class="flex items-start justify-between gap-4">
                    <dt class="text-ink-secondary">{{ __('platform::vendors.plan.price') }}</dt>
                    <dd class="text-end font-medium text-ink">{{ __('platform::vendors.plan.per_term', ['amount' => $subscription->amount()->format(), 'term' => $subscription->term->label()]) }}</dd>
                </div>
                <div class="flex items-start justify-between gap-4">
                    <dt class="text-ink-secondary">{{ $isTrial ? __('platform::vendors.plan.trial_ends') : __('platform::vendors.plan.renews') }}</dt>
                    <dd class="text-end font-medium text-ink">{{ ($isTrial ? $subscription->trial_ends_at : $subscription->current_period_ends_at)?->translatedFormat('j M Y') ?? '—' }}</dd>
                </div>
                <div class="flex items-start justify-between gap-4">
                    <dt class="text-ink-secondary">{{ __('platform::vendors.plan.payment_method') }}</dt>
                    <dd class="text-end font-medium text-ink">{{ $subscription->gateway?->label() ?? __('platform::vendors.plan.no_gateway') }}</dd>
                </div>
                <div class="flex items-start justify-between gap-4">
                    <dt class="text-ink-secondary">{{ __('platform::vendors.plan.billing') }}</dt>
                    <dd class="text-end font-medium text-ink">{{ $subscription->auto_renew ? __('platform::vendors.plan.automatic') : __('platform::vendors.plan.manual') }}</dd>
                </div>
            </dl>
        @else
            <x-ui::empty-state compact icon="star" :title="__('platform::vendors.plan.none_title')" :description="__('platform::vendors.plan.none_description')" />
        @endif
    </x-ui::card>

    <x-ui::card :title="__('platform::vendors.plan.history')" :padding="false" id="subscription-history">
        @if ($history->isEmpty())
            <div class="p-5">
                <x-ui::empty-state compact :title="__('platform::vendors.plan.history_empty_title')" :description="__('platform::vendors.plan.history_empty_description')" />
            </div>
        @else
            <div class="relative overflow-x-auto">
                <table class="w-full min-w-[640px] text-sm">
                    <thead class="bg-subtle text-start text-xs font-medium text-ink-secondary">
                        <tr>
                            <th scope="col" class="px-5 py-3 text-start font-medium">{{ __('platform::vendors.plan.columns.period') }}</th>
                            <th scope="col" class="px-5 py-3 text-start font-medium">{{ __('platform::vendors.plan.columns.plan') }}</th>
                            <th scope="col" class="px-5 py-3 text-end font-medium">{{ __('platform::vendors.plan.columns.amount') }}</th>
                            <th scope="col" class="px-5 py-3 text-start font-medium">{{ __('platform::vendors.plan.columns.status') }}</th>
                            <th scope="col" class="px-5 py-3 text-start font-medium">{{ __('platform::vendors.plan.columns.invoice') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($history as $row)
                            <tr class="border-t border-line">
                                <td class="px-5 py-4 text-ink">
                                    @if ($row->status === \App\Modules\Billing\Enums\SubscriptionStatus::Trial || ($row->current_period_starts_at === null && $row->trial_ends_at))
                                        {{ __('platform::vendors.plan.trial_period', ['date' => $row->trial_ends_at?->translatedFormat('j M Y') ?? '—']) }}
                                    @else
                                        {{ $row->current_period_starts_at?->translatedFormat('M Y') ?? '—' }} – {{ $row->current_period_ends_at?->translatedFormat('M Y') ?? '—' }}
                                    @endif
                                </td>
                                <td class="px-5 py-4 text-ink">{{ $row->plan?->name ?? '—' }}</td>
                                <td class="px-5 py-4 text-end font-medium text-ink"><bdi dir="ltr">{{ $row->amount()->format() }}</bdi></td>
                                <td class="px-5 py-4"><x-ui::badge :tone="$row->status->tone()">{{ $row->status->label() }}</x-ui::badge></td>
                                <td class="px-5 py-4">
                                    @if ($row->latestInvoice && $canInvoices)
                                        <a href="{{ route('admin.finance.invoices.show', $row->latestInvoice->id) }}" class="font-medium text-ink-brand hover:underline" dir="ltr">{{ $row->latestInvoice->number }}</a>
                                    @elseif ($row->latestInvoice)
                                        <span dir="ltr">{{ $row->latestInvoice->number }}</span>
                                    @else
                                        <span class="text-ink-muted">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-ui::card>
</div>

@if ($canAssign)
    <x-ui::modal id="assign-plan" :title="__('platform::vendors.plan.assign')" :subtitle="__('platform::vendors.plan.assign_hint')">
        @include('billing::admin.subscriptions._assign-form', ['activePlans' => $activePlans, 'terms' => $terms, 'tenantId' => $tenant->id])
        <x-slot:footer>
            <x-ui::button variant="secondary" data-overlay-close>{{ __('ui.cancel') }}</x-ui::button>
            <x-ui::button type="submit" form="assign-plan-form" icon="check">{{ __('platform::vendors.plan.assign') }}</x-ui::button>
        </x-slot:footer>
    </x-ui::modal>
@endif
