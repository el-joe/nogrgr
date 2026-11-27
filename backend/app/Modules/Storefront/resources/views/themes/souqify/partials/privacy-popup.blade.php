{{--
    "Why choose {store}" popup (layout of Elora m 1:23152 in Souqify tokens; centred dialog on desktop): segmented tabs
    "Order guarantee" / "Protection and privacy" ([data-simple-tabs], catalog/ui.js) with the
    store's payment, protection, privacy and delivery-guarantee commitments.
--}}
@php
    $store = \App\Modules\Storefront\Support\StorefrontTheme::storeName();
    $badges = app(\App\Modules\Storefront\Services\Catalog\StorefrontShell::class)->paymentBadges();
    $privacy = Route::has('storefront.pages.show') ? route('storefront.pages.show', ['slug' => 'privacy']) : null;
    $returns = Route::has('storefront.pages.show') ? route('storefront.pages.show', ['slug' => \App\Modules\Storefront\Enums\SystemStorePage::Returns->value]) : null;
@endphp
<x-sf::modal id="sq-privacy" :title="__st('souqify_pages.product.why_choose', ['store' => $store])" size="md">
    <div class="grid gap-5" data-simple-tabs>
        <div class="sq-seg" role="tablist" aria-label="{{ __st('souqify_pages.product.why_choose', ['store' => $store]) }}">
            <button type="button" role="tab" id="sq-pv-tab-1" aria-controls="sq-pv-panel-1" aria-selected="false">{{ __st('souqify_pages.product.order_guarantee') }}</button>
            <button type="button" role="tab" id="sq-pv-tab-2" aria-controls="sq-pv-panel-2" aria-selected="true">{{ __st('souqify_pages.product.protection') }}</button>
        </div>

        <section id="sq-pv-panel-1" role="tabpanel" aria-labelledby="sq-pv-tab-1" class="grid gap-4 text-sm text-sq-ink" hidden>
            <h3 class="flex items-center gap-2 text-base font-medium"><x-ui::icon name="truck-tick" :size="22" class="text-sq-green" />{{ __st('cart.trust.delivery') }}</h3>
            <ul class="grid gap-2" role="list">
                @foreach (['credit', 'damaged', 'no_update', 'no_delivery'] as $key)
                    <li class="flex items-start gap-2"><x-ui::icon name="check" :size="16" class="mt-0.5 shrink-0 text-sq-green" />{{ __st('cart.trust.guarantee.'.$key) }}</li>
                @endforeach
            </ul>
            <h3 class="flex items-center gap-2 text-base font-medium"><x-ui::icon name="security-time" :size="22" class="text-sq-green" />{{ __st('cart.trust.protection', ['store' => $store]) }}</h3>
            <p class="text-[#5e5e5e]">{{ __st('cart.trust.protection_body') }}</p>
            @if ($returns)
                <a href="{{ $returns }}" class="inline-flex w-fit items-center gap-1 text-sf-muted hover:text-sq-ink">{{ __st('cart.trust.program_terms') }}<x-ui::icon name="chevron-right" :size="14" class="rtl:-scale-x-100" /></a>
            @endif
        </section>

        <section id="sq-pv-panel-2" role="tabpanel" aria-labelledby="sq-pv-tab-2" class="grid gap-4 text-sm text-sq-ink">
            <h3 class="text-center text-lg">{{ __st('souqify_pages.product.protection') }}</h3>
            <div class="grid gap-3">
                <p class="flex items-center gap-3 text-base font-medium"><span class="grid size-6 place-items-center rounded-full bg-sq-green text-xs text-white">1</span>{{ __st('cart.trust.safe_payment') }}</p>
                <p class="text-[#5e5e5e]"><span class="text-sq-green">{{ __st('cart.trust.safe_payment_lead', ['store' => $store]) }}</span> {{ __st('cart.trust.safe_payment_body') }}</p>
                @if ($badges)
                    <ul class="flex flex-wrap gap-2" role="list">
                        @foreach ($badges as $badge)
                            <li class="sq-pay-chip">{{ __st('shell.payments.'.$badge) }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
            <div class="grid gap-3">
                <p class="flex items-center gap-3 text-base font-medium"><span class="grid size-6 place-items-center rounded-full bg-sq-green text-xs text-white">2</span>{{ __st('cart.trust.secure_privacy') }}</p>
                <p class="text-[#5e5e5e]">{{ __st('cart.trust.privacy_body') }}</p>
                @if ($privacy)
                    <a href="{{ $privacy }}" class="inline-flex w-fit items-center gap-1 text-sf-muted hover:text-sq-ink">{{ __st('cart.trust.learn_more') }}<x-ui::icon name="chevron-right" :size="14" class="rtl:-scale-x-100" /></a>
                @endif
            </div>
        </section>
    </div>
</x-sf::modal>
