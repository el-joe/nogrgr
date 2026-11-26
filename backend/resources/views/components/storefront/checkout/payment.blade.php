{{--
    Payment details — ported 1:1 from the old Ecommet checkout (spec 08 §1–§2; no
    redesign): "Payment details" title, one bordered radio row per gateway (alphabetical,
    nothing pre-selected; card icon, name, method chips xs, central logo), the error line,
    and the "no payment method" notice. Every gateway pays on its own hosted page (Stripe
    Checkout, PayPal): no card field and no third-party script on the store (decision #78 b).
    $gateways = Store API payment_methods items.
--}}
@props(['gateways' => []])
<section class="grid" data-payment-section aria-labelledby="sf-payment-title">
    <h2 id="sf-payment-title" class="mb-4 text-[20px] font-[500] text-[#242424]">{{ __st('checkout.payment.title') }}</h2>

    <div class="flex flex-col gap-2.5" role="radiogroup" aria-labelledby="sf-payment-title" aria-describedby="payment-method-error">
        @foreach ($gateways as $gateway)
            <label class="flex cursor-pointer items-center gap-3 rounded-[6px] border border-[#dcdcdc] bg-white px-4 py-3 transition hover:border-[#242424] has-[:checked]:border-[#242424]" data-gateway-option>
                <input type="radio" name="payment_choice" value="{{ $gateway['code'] }}" class="accent-black" data-gateway-radio data-mode="{{ $gateway['mode'] }}">
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
</section>
