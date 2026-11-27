{{--
    Vendor dashboard (Tenant Panel Layout, Dashboard 12:11267): welcome header (Customize
    store / View store), the store hero with "Store power" = setup progress and the
    readiness chips around the ring, the setup progress widget (VEN-008) and store status
    card, then the permission-aware widgets (VendorDashboardWidgetService): pay the
    platform (009a), opportunities + flash-sale modal (009c), new-in products (009d), AI ads
    "Successful advertisements" + AI Advertising Studio (009e / 010c), "Your store's
    performance" KPIs / period / charts / latest orders (009, analytics.view), live
    store preview by country (009f) and the partner banner (009b).
--}}
@use('App\Modules\Platform\Enums\SetupStep')
@php
    /** @var \App\Modules\Platform\DTOs\SetupProgressData $progress */
    $steps = collect($progress->steps)->keyBy(fn ($item) => $item->step->value);
    $customizeUrl = \App\Modules\Platform\Services\OnboardingService::accessibleVendorUrl('store.appearance');
    $checklistUrl = vendor_route('onboarding', ['tab' => 'checklist']);
    $chip = function (SetupStep $step) use ($steps): array {
        $item = $steps[$step->value];

        return [
            'label' => __('platform::dashboard_vendor.chips.'.$step->value),
            'state' => $item->state->isComplete() ? __('platform::dashboard_vendor.chip_states.'.$item->state->value) : __('platform::dashboard_vendor.chip_states.open'),
            'ready' => $item->state->isComplete(),
        ];
    };
    $chips = [
        'top' => $chip(SetupStep::Domain),
        'end' => $chip(SetupStep::PaymentReadiness),
        'start' => $chip(SetupStep::Theme),
        'bottom' => $chip(SetupStep::Logo),
    ];
    $highlights = collect($progress->steps)->filter(fn ($item) => $item->state->isComplete())->take(3);
    $radius = 62;
    $circumference = round(2 * M_PI * $radius, 2);
@endphp
<x-vendor::layout :title="__('platform::panel.dashboard')" page="vendor-dashboard">
    <header class="flex flex-wrap items-center justify-between gap-4" data-reveal="fade-up">
        <div class="grid min-w-0 gap-1">
            <h1 class="text-[26px] font-bold leading-[1.5] text-ink sm:text-[32px]">{{ __('platform::dashboard_vendor.welcome', ['store' => $tenant->name]) }}</h1>
            <p class="text-sm text-ink-muted">{{ __('platform::dashboard_vendor.subtitle') }}</p>
        </div>
        <div class="flex w-full flex-wrap gap-3 sm:w-auto sm:gap-6">
            @if ($customizeUrl)
                <a href="{{ $customizeUrl }}" class="vnd-cta vnd-cta-primary flex-1 sm:w-[211px] sm:flex-none"><x-ui::icon name="edit" :size="24" />{{ __('platform::dashboard_vendor.customize') }}</a>
            @endif
            <a href="{{ $storeUrl }}" target="_blank" rel="noopener" class="vnd-cta vnd-cta-outline flex-1 sm:w-[211px] sm:flex-none"><x-ui::icon name="eye" :size="24" />{{ __('platform::vendor.view_store') }}</a>
        </div>
    </header>

    <section class="vnd-hero relative isolate overflow-hidden rounded-[24px] p-4 text-[#f5f5f5]" aria-labelledby="hero-title" data-reveal="fade-up">
        <div class="relative flex min-h-[372px] flex-col items-center justify-end gap-10 px-2 py-6 sm:px-6 lg:flex-row lg:py-0 xl:px-10">
            <div class="flex w-full max-w-[426px] flex-col justify-center gap-[15px]">
                <h2 id="hero-title" class="text-lg font-bold leading-[1.5] sm:text-xl">{{ $progress->isComplete() ? __('platform::dashboard_vendor.hero.ready_title') : __('platform::dashboard_vendor.hero.setup_title') }}</h2>
                <p class="text-sm leading-[1.5] text-[#a3a3a3]">{{ $progress->isComplete() ? __('platform::dashboard_vendor.hero.ready_body') : trans_choice('platform::dashboard_vendor.hero.setup_body', $progress->remaining(), ['count' => $progress->remaining()]) }}</p>
                @if ($highlights->isNotEmpty())
                    <ul class="flex flex-wrap gap-x-[19px] gap-y-2 text-xs">
                        @foreach ($highlights as $item)
                            <li class="flex items-center gap-1"><x-ui::icon name="check-circle" :size="16" class="text-success" />{{ $item->title }}</li>
                        @endforeach
                    </ul>
                @endif
                <div class="mt-2 flex flex-wrap gap-4 sm:flex-nowrap sm:gap-6">
                    <a href="{{ $storeUrl }}" target="_blank" rel="noopener" class="vnd-cta vnd-cta-outline flex-1 rounded-[12px]"><x-ui::icon name="eye" :size="24" />{{ __('platform::vendor.view_store') }}</a>
                    <a href="{{ $checklistUrl }}" class="vnd-cta vnd-cta-primary flex-1 rounded-[12px]">{{ $progress->isComplete() ? __('platform::dashboard_vendor.hero.checklist') : __('platform::onboarding.widget.continue') }}<x-ui::icon name="chevron-right" :size="22" data-flip /></a>
                </div>
            </div>

            <div class="vnd-power relative my-8 grid size-[232px] shrink-0 place-items-center rounded-full border-2 border-primary lg:my-0" data-power="{{ $progress->percent() }}">
                <svg class="vnd-power-ring absolute inset-[42px] size-[148px] -rotate-90" viewBox="0 0 148 148" aria-hidden="true">
                    <circle cx="74" cy="74" r="{{ $radius }}" fill="none" stroke="currentColor" stroke-opacity=".18" stroke-width="3" />
                    <circle class="vnd-power-arc" cx="74" cy="74" r="{{ $radius }}" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-dasharray="{{ $circumference }}" stroke-dashoffset="{{ round($circumference * (1 - $progress->percent() / 100), 2) }}" />
                </svg>
                @foreach (['top', 'end', 'bottom', 'start'] as $dot)
                    <span class="vnd-power-dot vnd-power-dot-{{ $dot }}" aria-hidden="true"></span>
                @endforeach
                <p class="relative text-center leading-[1.5]">
                    <span class="block text-[44px] font-bold text-white" dir="ltr">{{ $progress->percent() }}%</span>
                    <span class="block text-[13px] text-white">{{ __('platform::dashboard_vendor.hero.power') }}</span>
                </p>
                @foreach ($chips as $position => $data)
                    <span class="vnd-power-chip vnd-power-chip-{{ $position }}">
                        <span class="block text-center text-[8px] text-white">{{ $data['label'] }}</span>
                        <span @class(['flex items-center gap-1 whitespace-nowrap text-xs', 'text-success' => $data['ready'], 'text-primary' => ! $data['ready']])><span class="size-[7px] rounded-full bg-current" aria-hidden="true"></span>{{ $data['state'] }}</span>
                    </span>
                @endforeach
            </div>
        </div>
    </section>

    @if ($performance !== null)
        @include('platform::vendor.dashboard._performance')
    @endif

    <div class="grid items-start gap-6 lg:grid-cols-[350px_minmax(0,1fr)]">
        <x-vendor::setup-progress :progress="$progress" />

        <section class="rounded-card border border-line bg-surface p-5 shadow-card" aria-labelledby="store-status-title" data-reveal="fade-up">
            <h2 id="store-status-title" class="text-base font-semibold text-ink">{{ __('platform::dashboard_vendor.status.title') }}</h2>
            <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-2">
                <div class="grid gap-1">
                    <dt class="text-xs text-ink-muted">{{ __('platform::dashboard_vendor.status.state') }}</dt>
                    <dd><x-ui::status :status="$tenant->status" /></dd>
                </div>
                <div class="grid min-w-0 gap-1">
                    <dt class="text-xs text-ink-muted">{{ __('platform::dashboard_vendor.status.address') }}</dt>
                    <dd class="truncate"><a href="{{ $storeUrl }}" target="_blank" rel="noopener" class="font-medium text-ink-brand hover:underline" dir="ltr">{{ $tenant->subdomainHost() }}</a></dd>
                </div>
                <div class="grid gap-1">
                    <dt class="text-xs text-ink-muted">{{ __('platform::dashboard_vendor.status.since') }}</dt>
                    <dd class="text-ink">{{ $tenant->created_at?->locale(app()->getLocale())->translatedFormat('j M Y') }}</dd>
                </div>
                <div class="grid gap-1">
                    <dt class="text-xs text-ink-muted">{{ __('platform::dashboard_vendor.status.setup') }}</dt>
                    <dd class="text-ink" dir="ltr">{{ __('platform::onboarding.widget.steps', ['done' => $progress->completed, 'total' => $progress->total]) }}</dd>
                </div>
            </dl>
        </section>
    </div>

    @include('platform::vendor.dashboard._pay-alert', ['owed' => $widgets->owed, 'failed' => $widgets->failed('owed')])
    @include('platform::vendor.dashboard._opportunities')
    @include('platform::vendor.dashboard._new-in')
    @include('ai::vendor.ad-studio.dashboard-widget')
    @include('platform::vendor.dashboard._storefront')

    @include('affiliates::vendor._partner')

    @if ($widgets->opportunities !== null)
        @include('commerce::vendor.flash-sales._attach-modal')
    @endif
</x-vendor::layout>
