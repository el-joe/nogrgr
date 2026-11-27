{{-- ADM-062 reject a payout request: optional note shown to the affiliate (MAIL-040). --}}
<form id="payout-reject-form" method="POST" action="{{ route('admin.affiliates.payouts.reject', $payoutRequest->id) }}" data-ajax novalidate class="grid gap-4"
    data-modal-title="{{ __('affiliates::admin.payouts.reject_title', ['reference' => $payoutRequest->reference()]) }}" data-modal-subtitle="{{ $payoutRequest->affiliate->name ?? '' }}">
    @csrf
    <x-ui::textarea name="note" :label="__('affiliates::admin.payouts.reject_note')" rows="4" maxlength="1000" />
    @include('commerce::partials.modal-actions', ['submit' => __('affiliates::admin.payouts.reject'), 'cancel' => __('affiliates::admin.cancel'), 'tone' => 'danger', 'icon' => 'x-circle'])
</form>
