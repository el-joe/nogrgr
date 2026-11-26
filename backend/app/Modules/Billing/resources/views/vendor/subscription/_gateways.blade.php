{{-- Gateway radios of a checkout modal ($gateways, $prefix keeps the input ids unique). --}}
<fieldset class="grid gap-3">
    <legend class="mb-2 text-sm font-medium text-ink">{{ __('billing::vendor_subscription.gateway') }}</legend>
    @foreach ($gateways as $gateway)
        <x-ui::radio name="gateway" :id="$prefix.'-gateway-'.$gateway->code->value" :value="$gateway->code->value" :label="$gateway->code->brand()" :checked="$loop->first" />
    @endforeach
    @error('gateway')
        <p class="text-xs text-danger">{{ $message }}</p>
    @enderror
</fieldset>
<p class="flex items-start gap-2 text-xs text-ink-secondary">
    <x-ui::icon name="shield" :size="16" class="shrink-0" />{{ __('billing::vendor_subscription.secure_checkout') }}
</p>
