{{--
    "Your referral link" card (AFF-009 / VEN-055): link + copy, promo code + copy (when
    one exists), commission rate, cookie window, minimum payout.
    Inputs: $overview (AffiliatePortalService::overview), $promoCode (?AffiliatePromoCode),
    optional $linksUrl ("Manage links" when the links page is reachable).
--}}
<x-ui::card :title="__('affiliates::panel.referral.title')" :subtitle="trans_choice('affiliates::panel.referral.subtitle', $overview['cookieDays'], ['days' => $overview['cookieDays']])" id="referral-card">
    <div class="grid gap-3">
        <div class="flex items-center gap-3 rounded-control border border-line bg-subtle px-4 py-3">
            <x-ui::icon name="link" :size="18" class="shrink-0 text-ink-brand" />
            <span class="min-w-0 flex-1 truncate text-sm font-medium text-ink" dir="ltr" data-referral-link>{{ $overview['link'] }}</span>
            <x-ui::button size="sm" variant="secondary" icon="copy" :data-copy-text="$overview['link']">{{ __('affiliates::panel.referral.copy') }}</x-ui::button>
        </div>
        @if ($promoCode)
            <div class="flex items-center gap-3 rounded-control border border-line bg-subtle px-4 py-3">
                <x-ui::icon name="tag" :size="18" class="shrink-0 text-ink-brand" />
                <span class="min-w-0 flex-1 truncate text-sm font-medium text-ink">{{ __('affiliates::panel.referral.promo', ['code' => $promoCode->code]) }} · <bdi dir="ltr">{{ $promoCode->discountLabel() }}</bdi></span>
                <x-ui::button size="sm" variant="secondary" icon="copy" :data-copy-text="$promoCode->code">{{ __('affiliates::panel.referral.copy') }}</x-ui::button>
            </div>
        @endif
    </div>
    <dl class="mt-4 grid gap-2 text-sm">
        <div class="flex items-center justify-between gap-3">
            <dt class="text-ink-secondary">{{ __('affiliates::panel.referral.commission') }}</dt>
            <dd class="font-semibold text-ink" dir="ltr">{{ $overview['commission'] }}</dd>
        </div>
        <div class="flex items-center justify-between gap-3">
            <dt class="text-ink-secondary">{{ __('affiliates::panel.referral.pending_requests') }}</dt>
            <dd class="font-semibold text-ink" dir="ltr">{{ $overview['pendingRequests']->format() }}</dd>
        </div>
        <div class="flex items-center justify-between gap-3">
            <dt class="text-ink-secondary">{{ __('affiliates::panel.referral.minimum') }}</dt>
            <dd class="font-semibold text-ink" dir="ltr">{{ $overview['minimum']->format() }}</dd>
        </div>
    </dl>
    @if (! empty($linksUrl))
        <a href="{{ $linksUrl }}" class="mt-4 inline-flex text-[13px] font-medium text-ink-brand hover:underline">{{ __('affiliates::panel.referral.manage') }}</a>
    @endif
</x-ui::card>
