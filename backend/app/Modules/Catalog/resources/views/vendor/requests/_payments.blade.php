{{--
    Payment requests of a manufacturing / brand request (Figma 87:110114 "Payment request"
    card): label, amount, requested / due dates and "Pay $x" through the hosted checkout
    (one of the vendor-payment gateways). Paid / cancelled rows stay listed.
--}}
@use('App\Modules\Catalog\Enums\PaymentRequestStatus')
@php
    $locale = app()->getLocale();
    $gatewayOptions = $gateways->mapWithKeys(fn ($gateway) => [$gateway->code->value => $gateway->code->label()])->all();
@endphp
<x-ui::card :title="__('catalog::vendor_requests.payment.title')" id="request-payments">
    @forelse ($payments as $payment)
        @php($pending = $payment->status === PaymentRequestStatus::Pending)
        <div class="grid gap-3 border-b border-line py-3 first:pt-0 last:border-b-0 last:pb-0" data-payment-row="{{ $payment->id }}">
            <div class="flex items-start justify-between gap-3">
                <span class="text-sm font-medium text-ink">{{ $payment->label }}</span>
                @if ($pending && $payment->due_at)
                    <x-ui::badge tone="warning" dot>{{ __('catalog::vendor_requests.payment.due', ['date' => $payment->due_at->locale($locale)->translatedFormat('j M')]) }}</x-ui::badge>
                @else
                    <x-ui::badge :tone="$payment->status->tone()">{{ $payment->status->label() }}</x-ui::badge>
                @endif
            </div>
            <dl class="grid gap-2 text-sm">
                <div class="flex justify-between gap-3"><dt class="text-ink-secondary">{{ $payment->label }}</dt><dd class="font-semibold text-ink" dir="ltr">{{ $payment->amount->format() }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-ink-secondary">{{ __('catalog::vendor_requests.payment.requested') }}</dt><dd class="font-medium text-ink">{{ $payment->created_at?->locale($locale)->translatedFormat('j M') }}</dd></div>
                @if ($payment->status === PaymentRequestStatus::Paid)
                    <div class="flex justify-between gap-3"><dt class="text-ink-secondary">{{ __('catalog::vendor_requests.result.paid') }}</dt><dd class="font-medium text-success">{{ __('catalog::vendor_requests.payment.paid_on', ['date' => $payment->paid_at?->locale($locale)->translatedFormat('j M')]) }}</dd></div>
                @elseif ($payment->status === PaymentRequestStatus::Cancelled)
                    <div class="flex justify-between gap-3"><dt class="text-ink-secondary">{{ __('catalog::vendor_requests.result.cancelled') }}</dt><dd class="font-medium text-ink-secondary">{{ __('catalog::vendor_requests.payment.cancelled_on', ['date' => $payment->cancelled_at?->locale($locale)->translatedFormat('j M')]) }}</dd></div>
                @endif
                @if ($payment->invoice)
                    <div class="flex justify-between gap-3"><dt class="text-ink-secondary">{{ __('catalog::vendor_requests.result.invoice') }}</dt><dd class="font-medium text-ink" dir="ltr">{{ $payment->invoice->number }}</dd></div>
                @endif
            </dl>
            @if ($pending && $canManage)
                @if ($gatewayOptions === [])
                    <p class="rounded-control bg-subtle p-3 text-xs text-ink-secondary">{{ __('catalog::vendor_requests.payment.no_gateways') }}</p>
                @else
                    <form method="POST" action="{{ vendor_route('requests.'.$request->kind()->value.'.payments.pay', ['id' => $request->id, 'payment' => $payment->id]) }}" class="grid gap-3">
                        @csrf
                        @if (count($gatewayOptions) === 1)
                            <input type="hidden" name="gateway" value="{{ array_key_first($gatewayOptions) }}">
                        @else
                            <x-ui::radio-group name="gateway" :label="__('catalog::vendor_requests.payment.gateway')" :options="$gatewayOptions" :value="array_key_first($gatewayOptions)" required />
                        @endif
                        <x-ui::stripe-card :gateways="$gateways" :purpose="\App\Modules\Integrations\Enums\GatewayPurpose::VendorPayments" />
                        <x-ui::button type="submit" icon="credit-card" class="justify-self-start">{{ __('catalog::vendor_requests.payment.pay', ['amount' => $payment->amount->format()]) }}</x-ui::button>
                    </form>
                @endif
            @endif
        </div>
    @empty
        <p class="text-sm text-ink-secondary">{{ __('catalog::vendor_requests.payment.empty') }}</p>
    @endforelse
</x-ui::card>
