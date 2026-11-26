{{--
    ADM-060b / 062 "Pay out — <affiliate>" (Figma 74:68877): balance + minimum payout,
    amount (USD), method, reference, proof upload, notes; Reject (request only) / Mark paid.
    $affiliate, $payoutRequest (nullable), $minimum, $methods, $formAction, $rejectUrl.
--}}
@php
    $balance = $affiliate->balanceMoney();
    $suggested = $payoutRequest !== null ? $payoutRequest->money()->min($balance) : $balance;
@endphp
<form id="affiliate-payout-form" method="POST" action="{{ $formAction }}" enctype="multipart/form-data" data-ajax class="grid gap-4"
    data-modal-title="{{ __('affiliates::admin.payout.title', ['name' => $affiliate->name]) }}"
    data-modal-subtitle="{{ __('affiliates::admin.payout.subtitle', ['balance' => $balance->format(), 'minimum' => $minimum->format()]) }}">
    @csrf
    @if ($payoutRequest !== null)
        <p class="rounded-control bg-subtle px-3 py-2 text-sm text-ink">{{ __('affiliates::admin.payout.requested', ['amount' => $payoutRequest->money()->format(), 'reference' => $payoutRequest->reference()]) }}@if ($payoutRequest->note)<span class="mt-1 block text-ink-secondary">{{ $payoutRequest->note }}</span>@endif</p>
    @endif
    @unless ($balance->isPositive())
        <p class="rounded-control bg-warning-bg px-3 py-2 text-sm text-warning" role="status">{{ __('affiliates::admin.payout.nothing_due') }}</p>
    @endunless
    <x-ui::input name="amount" type="number" step="0.01" min="0.01" :max="$balance->isPositive() ? $balance->toDecimal() : null" :value="$suggested->isPositive() ? $suggested->toDecimal() : null" :label="__('affiliates::admin.payout.amount')" required dir="ltr" />
    <x-ui::select name="method" :label="__('affiliates::admin.payout.method')" :options="$methods" :selected="$affiliate->payout_method->value ?? 'bank_transfer'" :search="false" required />
    <x-ui::input name="reference" :label="__('affiliates::admin.payout.reference')" maxlength="120" dir="ltr" />
    <x-ui::file-upload name="attachment" :label="__('affiliates::admin.payout.attachment')" accept=".pdf,.png,.jpg,.jpeg,.zip" :hint="__('affiliates::admin.payout.attachment_hint')" />
    <x-ui::textarea name="notes" :label="__('affiliates::admin.payout.notes')" rows="2" maxlength="2000" />
    <div class="-mx-5 -mb-5 mt-2 flex justify-end gap-2 border-t border-line bg-subtle px-5 py-4 sm:-mx-6 sm:-mb-6 sm:px-6">
        @if ($rejectUrl)
            <x-ui::button variant="danger" icon="x-circle" data-remote-modal="affiliate-modal" :data-url="$rejectUrl">{{ __('affiliates::admin.payouts.reject') }}</x-ui::button>
        @else
            <x-ui::button variant="secondary" data-overlay-close>{{ __('affiliates::admin.cancel') }}</x-ui::button>
        @endif
        <x-ui::button type="submit" icon="check" :disabled="! $balance->isPositive()">{{ __('affiliates::admin.payout.mark_paid') }}</x-ui::button>
    </div>
</form>
