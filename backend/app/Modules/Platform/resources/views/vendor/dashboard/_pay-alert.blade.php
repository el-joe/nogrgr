{{--
    VEN-009a (Dashboard 12:11267 pay bar): what the store owes the platform (ledger
    receivable, USD) with "Pay now" → Money flow › Purchases, where each order is settled
    (VEN-021d). Settled stores get a quiet confirmation instead of the alert.
    Data: $owed (OwedToPlatformData|null), $failed (bool).
--}}
@if ($failed)
    @include('platform::vendor.dashboard._widget-error')
@elseif ($owed !== null && ! $owed->isSettled())
    <section class="vnd-pay-alert flex flex-wrap items-center justify-between gap-4 rounded-[20px] px-4 py-5 text-white sm:px-5" aria-labelledby="pay-alert-title" role="status" data-reveal="fade-up" data-dashboard-widget="owed">
        <div class="flex min-w-0 items-center gap-4">
            <span class="grid size-14 shrink-0 place-items-center rounded-[12px] bg-white/10 text-primary"><x-ui::icon name="credit-card" :size="28" /></span>
            <div class="grid min-w-0 gap-1">
                <h2 id="pay-alert-title" class="text-lg font-bold leading-[1.4] text-primary sm:text-xl">{!! __('platform::dashboard_vendor.pay.title', ['amount' => '<span dir="ltr">'.e($owed->amount->format()).'</span>']) !!}</h2>
                <p class="text-sm text-white/85">{{ trans_choice('platform::dashboard_vendor.pay.orders', $owed->orders, ['count' => $owed->orders]) }}</p>
            </div>
        </div>
        <a href="{{ $owed->payUrl }}" class="vnd-cta vnd-cta-primary w-full rounded-[12px] sm:w-[182px]">{{ __('platform::dashboard_vendor.pay.cta') }}<x-ui::icon name="arrow-right" :size="22" data-flip /></a>
    </section>
@elseif ($owed !== null)
    <section class="flex flex-wrap items-center justify-between gap-3 rounded-card border border-line bg-surface px-5 py-4 shadow-card" aria-labelledby="pay-settled-title" data-dashboard-widget="owed">
        <div class="flex items-center gap-3">
            <x-ui::icon name="check-circle" :size="24" class="text-success" />
            <div class="grid gap-0.5">
                <h2 id="pay-settled-title" class="text-sm font-semibold text-ink">{{ __('platform::dashboard_vendor.pay.settled_title') }}</h2>
                <p class="text-xs text-ink-muted">{{ __('platform::dashboard_vendor.pay.settled_body') }}</p>
            </div>
        </div>
        <a href="{{ $owed->payUrl }}" class="text-sm font-medium text-ink-brand hover:underline">{{ __('platform::dashboard_vendor.pay.view') }}</a>
    </section>
@endif
