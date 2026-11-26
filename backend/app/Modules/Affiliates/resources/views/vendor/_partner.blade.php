{{--
    Vendor dashboard "Partner program" banner (VEN-009b, Figma 12:11267 `_partner`):
    commission pitch, the store's referral link with copy, link to VEN-055. Rendered only
    for staff with partner.view; data from the Affiliates view composer ($partner).
--}}
@if (! empty($partner))
    <section class="relative overflow-hidden rounded-card bg-linear-to-br from-[var(--nog-highlight-from)] to-[var(--nog-highlight-to)] p-5 text-white shadow-raised" aria-labelledby="partner-widget-title" data-reveal="fade-up" data-partner-widget>
        <div class="flex flex-wrap items-center gap-4">
            <span class="grid size-11 shrink-0 place-items-center rounded-control bg-white/15"><x-ui::icon name="gift" :size="22" /></span>
            <div class="grid min-w-0 flex-1 gap-1">
                <h2 id="partner-widget-title" class="text-base font-semibold">{{ __('affiliates::panel.partner.widget_title') }}</h2>
                <p class="text-sm text-white/85">{{ __('affiliates::panel.partner.widget_text', ['commission' => $partner['commission'], 'balance' => $partner['balance']]) }}</p>
            </div>
            <div class="flex min-w-0 flex-wrap items-center gap-2">
                <span class="max-w-[260px] truncate rounded-control bg-white/15 px-3 py-2 text-sm font-medium" dir="ltr">{{ $partner['link'] }}</span>
                <button type="button" class="inline-flex h-9 items-center gap-1.5 rounded-control bg-white px-3 text-[13px] font-medium text-ink hover:bg-white/90" data-copy-text="{{ $partner['link'] }}">
                    <x-ui::icon name="copy" :size="16" />{{ __('affiliates::panel.referral.copy') }}
                </button>
                <a href="{{ $partner['url'] }}" class="inline-flex h-9 items-center gap-1.5 rounded-control border border-white/40 px-3 text-[13px] font-medium hover:bg-white/10">{{ __('affiliates::panel.partner.widget_open') }}<x-ui::icon name="chevron-right" :size="16" data-flip /></a>
            </div>
        </div>
    </section>
@endif
