{{--
    Payment details — ported 1:1 from the old Ecommet checkout (spec 08 §1–§2; no
    redesign): "Payment details" title, one bordered radio row per gateway (alphabetical,
    nothing pre-selected; card icon, name, method chips xs, central logo), the error line,
    the "no payment method" notice, and the inline card panel shown under the list only
    for Stripe (Stripe.js Card Element, loaded on demand by core/payments/stripe-card.js —
    decision #78 b). PayPal pays on its hosted page.
    $gateways = Store API payment_methods items; $charge = the total (MoneyPresenter output):
    when the shopper sees another currency, "You will be charged $X USD." (USD is the only
    payment currency, decision #87; the checkout fragment keeps it current).
--}}
@props(['gateways' => [], 'charge' => null])
@php($usdCharge = \App\Modules\Storefront\Services\Checkout\StorefrontCart::usdCharge($charge))
<section class="grid" data-payment-section aria-labelledby="sf-payment-title">
    <h2 id="sf-payment-title" class="mb-4 text-[20px] font-[500] text-[#242424]">{{ __st('checkout.payment.title') }}</h2>

    <div class="flex flex-col gap-2.5" role="radiogroup" aria-labelledby="sf-payment-title" aria-describedby="payment-method-error">
        @foreach ($gateways as $gateway)
            <label class="flex cursor-pointer items-center gap-3 rounded-[6px] border border-[#dcdcdc] bg-white px-4 py-3 transition hover:border-[#242424] has-[:checked]:border-[#242424]" data-gateway-option>
                <input type="radio" name="payment_choice" value="{{ $gateway['code'] }}" class="accent-black" data-gateway-radio
                    data-inline="{{ ($gateway['inline'] ?? false) ? '1' : '0' }}" @if ($gateway['publishable_key'] ?? null) data-publishable-key="{{ $gateway['publishable_key'] }}" @endif data-mode="{{ $gateway['mode'] }}">
                <span class="flex flex-1 items-center gap-2.5">
                    <svg class="size-4 shrink-0 text-[#555]" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                    <span class="flex flex-col gap-1">
                        <span class="text-[14px] font-medium text-[#242424]">{{ $gateway['name'] }}</span>
                        <x-sf::checkout.method-badges :methods="$gateway['payment_methods']" size="xs" />
                    </span>
                    @if ($gateway['logo_url'])
                        <img src="{{ $gateway['logo_url'] }}" alt="{{ $gateway['name'] }}" class="ms-auto h-6 object-contain" loading="lazy">
                    @endif
                </span>
            </label>
        @endforeach
    </div>

    <p id="payment-method-error" class="mt-2 text-[11px] text-[#dc2626]" data-payment-error role="alert" hidden></p>

    @if ($gateways === [])
        <p class="mt-4 text-center text-sm font-medium text-red-600" data-no-gateway>{{ __st('checkout.payment.none') }}</p>
    @endif

    <p class="mt-3 flex items-start gap-1.5 rounded-[6px] bg-[#f5f5f5] px-3 py-2 text-[12px] text-[#555]" data-usd-charge role="note" @if ($usdCharge === null) hidden @endif>
        <svg class="mt-px size-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <span data-usd-charge-text>{{ $usdCharge }}</span>
    </p>

    {{-- Inline card panel: shown for the selected inline gateway (Stripe) only. --}}
    <div id="inline-card-form" class="mt-4 rounded-[8px] border border-[#dcdcdc] bg-[#fafafe] p-4" data-inline-card
        data-locale="{{ app()->getLocale() }}" data-card-error-text="{{ __st('checkout.card.error') }}" hidden>
        <h4 class="mb-3 flex items-center gap-1.5 text-[13px] font-semibold text-[#333]">
            <svg class="size-3.5 shrink-0 text-[#16a34a]" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
            {{ __st('checkout.card.title') }}
        </h4>
        <div id="stripe-card-element" class="block min-h-[42px] w-full rounded-[6px] border border-[#dcdcdc] bg-white px-4 py-3 text-[#333]" data-stripe-element role="group" aria-label="{{ __st('checkout.card.number') }}" dir="ltr"></div>
        <p id="stripe-card-errors" class="mt-1 text-[11px] text-[#dc2626]" data-stripe-error role="alert" hidden></p>
    </div>
</section>
