{{--
    Owner portal › My stores (OWN-001, Figma Affiliate & Owner 122:102288 · mobile
    122:105386): summary tiles, one card per store with its state, plan, renewal /
    trial / unpaid details and the actions "Open store panel" (SSO into the store's
    vendor panel), "Choose a plan" / "Pay invoice" and "Visit store".
    Inputs: $cards (OwnerPortalService::cards), $summary.
--}}
@use('App\Modules\Platform\Enums\OwnerStoreState')
@php
    $number = fn (int $value) => \Illuminate\Support\Number::format($value, locale: \App\Shared\Support\Locale::numberLocale());
    $date = fn (?\Illuminate\Support\Carbon $value) => $value?->translatedFormat('d M Y');
@endphp
<x-layouts.panel surface="owner" :title="__('platform::owner.dashboard.title')" data-page="owner-dashboard">
    <x-ui::page-header :title="__('platform::owner.dashboard.title')" :subtitle="__('platform::owner.dashboard.subtitle')">
        <x-slot:actions>
            <x-ui::button icon="plus" :href="route('owner.stores.create')">{{ __('platform::owner.dashboard.add_store') }}</x-ui::button>
        </x-slot:actions>
    </x-ui::page-header>

    @if ($cards === [])
        <x-ui::card>
            <x-ui::empty-state icon="store" :title="__('platform::owner.dashboard.empty_title')" :description="__('platform::owner.dashboard.empty_description')">
                <x-ui::button icon="plus" :href="route('owner.stores.create')">{{ __('platform::owner.dashboard.add_store') }}</x-ui::button>
            </x-ui::empty-state>
        </x-ui::card>
    @else
        <dl class="grid grid-cols-2 gap-4 lg:grid-cols-4" data-reveal>
            <div class="grid gap-1 rounded-card bg-subtle px-4 py-3">
                <dt class="text-xs text-ink-secondary">{{ __('platform::owner.dashboard.stats.stores') }}</dt>
                <dd class="text-xl font-semibold text-ink">{{ $number($summary['stores']) }}</dd>
            </div>
            <div class="grid gap-1 rounded-card bg-subtle px-4 py-3">
                <dt class="text-xs text-ink-secondary">{{ __('platform::owner.dashboard.stats.live') }}</dt>
                <dd class="text-xl font-semibold text-success">{{ $number($summary['live']) }}</dd>
            </div>
            <div class="grid gap-1 rounded-card bg-subtle px-4 py-3">
                <dt class="text-xs text-ink-secondary">{{ __('platform::owner.dashboard.stats.attention') }}</dt>
                <dd @class(['text-xl font-semibold', 'text-warning' => $summary['attention'] > 0, 'text-ink' => $summary['attention'] === 0])>{{ $number($summary['attention']) }}</dd>
            </div>
            <div class="grid gap-1 rounded-card bg-subtle px-4 py-3">
                <dt class="text-xs text-ink-secondary">{{ __('platform::owner.dashboard.stats.next_renewal') }}</dt>
                <dd class="text-xl font-semibold text-ink">{{ $date($summary['nextRenewal']) ?? '—' }}</dd>
            </div>
        </dl>

        <div class="grid items-start gap-6 lg:grid-cols-2">
            @foreach ($cards as $card)
                @php
                    $tenant = $card['tenant'];
                    $state = $card['state'];
                    $subscription = $card['subscription'];
                    $invoice = $card['invoice'];
                    $plan = $subscription ? trim((string) $subscription->plan?->name.' · '.($state === OwnerStoreState::Trial ? __('platform::owner.dashboard.trial') : $subscription->term->label())) : __('platform::owner.dashboard.no_plan');
                    $visitUrl = 'https://'.$tenant->subdomainHost();
                @endphp
                <article class="grid gap-4 rounded-card border border-line bg-surface p-5 shadow-card" data-store-card="{{ $tenant->slug }}" data-reveal>
                    <header class="flex items-start gap-3">
                        <span class="grid size-11 shrink-0 place-items-center rounded-full bg-primary text-sm font-semibold text-on-primary" aria-hidden="true">{{ mb_strtoupper(mb_substr($tenant->name, 0, 2)) }}</span>
                        <div class="grid min-w-0 flex-1">
                            <h2 class="truncate text-base font-semibold text-ink">{{ $tenant->name }}</h2>
                            <bdi dir="ltr" class="truncate text-xs text-ink-secondary">{{ implode(' · ', $card['hosts']) }}</bdi>
                        </div>
                        <x-ui::badge :tone="$state->tone()">{{ $state->label() }}</x-ui::badge>
                    </header>

                    <dl class="grid grid-cols-3 gap-3 text-sm">
                        <div class="grid gap-0.5">
                            <dt class="text-xs text-ink-secondary">{{ __('platform::owner.dashboard.plan') }}</dt>
                            <dd class="font-medium text-ink">{{ $plan }}</dd>
                        </div>
                        @if ($state === OwnerStoreState::Suspended && $invoice)
                            <div class="grid gap-0.5">
                                <dt class="text-xs text-ink-secondary">{{ __('platform::owner.dashboard.unpaid') }}</dt>
                                <dd class="font-medium text-ink"><bdi dir="ltr">{{ $invoice->total()->format() }}</bdi></dd>
                            </div>
                            <div class="grid gap-0.5">
                                <dt class="text-xs text-ink-secondary">{{ __('platform::owner.dashboard.since') }}</dt>
                                <dd class="font-medium text-ink">{{ $date($tenant->suspended_at ?? $invoice->issued_at) ?? '—' }}</dd>
                            </div>
                        @else
                            <div class="grid gap-0.5">
                                <dt class="text-xs text-ink-secondary">{{ __('platform::owner.dashboard.created') }}</dt>
                                <dd class="font-medium text-ink">{{ $date($tenant->created_at) ?? '—' }}</dd>
                            </div>
                            <div class="grid gap-0.5">
                                <dt class="text-xs text-ink-secondary">{{ $state === OwnerStoreState::Trial ? __('platform::owner.dashboard.trial_ends') : __('platform::owner.dashboard.renews') }}</dt>
                                <dd class="font-medium text-ink">{{ $date($subscription?->nextBillingAt()) ?? '—' }}</dd>
                            </div>
                        @endif
                    </dl>

                    @if ($state === OwnerStoreState::Trial && $card['trialDaysLeft'] !== null)
                        <p class="flex items-start gap-2 rounded-control bg-warning-bg px-4 py-3 text-sm text-ink">
                            <x-ui::icon name="alert-triangle" :size="18" class="shrink-0 text-warning" />{{ trans_choice('platform::owner.dashboard.trial_notice', $card['trialDaysLeft'], ['days' => $number($card['trialDaysLeft'])]) }}
                        </p>
                    @elseif ($state === OwnerStoreState::Suspended)
                        <p class="flex items-start gap-2 rounded-control bg-danger-bg px-4 py-3 text-sm text-ink">
                            <x-ui::icon name="x-circle" :size="18" class="shrink-0 text-danger" />
                            {{ $invoice ? __('platform::owner.dashboard.suspended_invoice', ['number' => $invoice->number]) : __('platform::owner.dashboard.suspended') }}
                        </p>
                    @elseif ($state === OwnerStoreState::PastDue)
                        <p class="flex items-start gap-2 rounded-control bg-warning-bg px-4 py-3 text-sm text-ink">
                            <x-ui::icon name="alert-triangle" :size="18" class="shrink-0 text-warning" />{{ __('platform::owner.dashboard.past_due') }}
                        </p>
                    @elseif ($state === OwnerStoreState::NotLaunched)
                        <p class="flex items-start gap-2 rounded-control bg-subtle px-4 py-3 text-sm text-ink-secondary">
                            <x-ui::icon name="clock" :size="18" class="shrink-0" />{{ __('platform::owner.dashboard.not_launched') }}
                        </p>
                    @endif

                    <div class="grid grid-cols-2 gap-3">
                        @if ($state === OwnerStoreState::Trial)
                            <x-ui::button icon="credit-card" :href="route('owner.subscriptions.index')">{{ __('platform::owner.dashboard.choose_plan') }}</x-ui::button>
                        @elseif ($state === OwnerStoreState::Suspended && $invoice)
                            <x-ui::button icon="credit-card" :href="route('owner.invoices.show', $invoice->id)">{{ __('platform::owner.dashboard.pay_invoice') }}</x-ui::button>
                        @elseif ($state->canOpenPanel())
                            <form method="POST" action="{{ route('owner.stores.open', $tenant->id) }}" class="contents">
                                @csrf
                                <x-ui::button type="submit" icon="log-out" class="rtl:[&_svg]:rotate-180">{{ __('platform::owner.dashboard.open_panel') }}</x-ui::button>
                            </form>
                        @else
                            <x-ui::button icon="log-out" disabled>{{ __('platform::owner.dashboard.open_panel') }}</x-ui::button>
                        @endif
                        <x-ui::button variant="secondary" icon="globe" :href="$visitUrl" target="_blank" rel="noopener" :disabled="$state === OwnerStoreState::NotLaunched">{{ __('platform::owner.dashboard.visit_store') }}</x-ui::button>
                    </div>
                    @if ($state === OwnerStoreState::Trial)
                        <form method="POST" action="{{ route('owner.stores.open', $tenant->id) }}">
                            @csrf
                            <button type="submit" class="text-sm font-medium text-ink-brand hover:underline">{{ __('platform::owner.dashboard.open_panel') }}</button>
                        </form>
                    @endif
                </article>
            @endforeach
        </div>
    @endif
</x-layouts.panel>
