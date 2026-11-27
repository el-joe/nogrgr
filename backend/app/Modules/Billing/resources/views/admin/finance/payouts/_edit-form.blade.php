{{-- ADM-052b "Edit payout PO-…" (Figma 74:68877): status (allowed next steps) and reference. --}}
<form id="payout-edit-form" method="POST" action="{{ $formAction }}" data-ajax novalidate class="grid gap-4"
    data-modal-title="{{ __('billing::finance.payouts.edit_title', ['reference' => $payout->reference]) }}">
    @csrf
    @method('PUT')
    <x-ui::select name="status" :label="__('billing::finance.payouts.fields.status')" :options="$statuses" :selected="$payout->status->value" :search="false" required />
    <x-ui::input name="transaction_reference" :label="__('billing::finance.payouts.fields.reference')" :value="$payout->transaction_reference" maxlength="120" :helper="__('billing::finance.payouts.reference_help')" dir="ltr" />
    @if ($payout->status === \App\Modules\Billing\Enums\PayoutStatus::Paid)
        <p class="rounded-control bg-warning-bg px-3 py-2 text-sm text-warning" role="status">{{ __('billing::finance.payouts.cancel_paid_message') }}</p>
    @endif
    @include('commerce::partials.modal-actions', ['submit' => __('billing::finance.save'), 'cancel' => __('billing::finance.cancel')])
</form>
