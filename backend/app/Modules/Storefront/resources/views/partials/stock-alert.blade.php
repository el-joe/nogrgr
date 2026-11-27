{{--
    Back-in-stock form of an out-of-stock product page (decision #124), shared by the
    Ecommet, Elora and Souqify product pages: a guest enters an e-mail, a signed-in
    customer subscribes with one tap (account e-mail, app push). Posts to
    store-api.v1.products.stock-alerts.store; JS: storefront/catalog/stock-alert.js.
    Data: $productId, $buttonClass (theme primary button), $inputClass (theme input).
--}}
@php
    $customer = auth('customer')->user();
    $alertUrl = route('store-api.v1.products.stock-alerts.store', ['storeProduct' => $productId]);
@endphp
<form class="grid gap-2 rounded-lg border border-current/15 p-3" method="POST" action="{{ $alertUrl }}" data-stock-alert data-signed-in="{{ $customer !== null ? '1' : '0' }}" novalidate>
    <div class="grid gap-0.5">
        <p class="text-sm font-semibold">{{ __st('product.stock_alert.title') }}</p>
        <p class="text-xs opacity-75" data-stock-alert-help>{{ $customer !== null ? __st('product.stock_alert.help_signed_in') : __st('product.stock_alert.help') }}</p>
    </div>
    <div class="flex flex-wrap items-start gap-2" data-stock-alert-fields>
        @if ($customer === null)
            <label class="grid min-w-0 flex-1 basis-48 gap-1">
                <span class="sr-only">{{ __st('product.stock_alert.email') }}</span>
                <input type="email" name="email" required autocomplete="email" inputmode="email" dir="ltr" maxlength="191" placeholder="{{ __st('product.stock_alert.email_placeholder') }}" class="{{ $inputClass ?? 'h-10 w-full rounded-lg border border-current/20 bg-transparent px-3 text-sm' }}">
                <span class="text-xs text-sf-danger" data-error-for="email" hidden></span>
            </label>
        @endif
        <button type="submit" class="{{ $buttonClass ?? 'h-10 rounded-lg px-4 text-sm font-medium' }}">{{ __st('product.stock_alert.submit') }}</button>
    </div>
    <p class="text-xs text-sf-danger" data-stock-alert-error role="alert" hidden></p>
    <p class="text-sm font-medium" data-stock-alert-done role="status" data-already="{{ __st('product.stock_alert.already') }}" hidden></p>
</form>
