{{-- ADM-050a "Mark settlement paid" (Figma 74:68877): amount (USD), method, reference, proof upload, note. --}}
@php
    $owed = \App\Shared\Money\Money::ofMinor($summary['owed']);
@endphp
<form id="settle-form" method="POST" action="{{ $formAction }}" enctype="multipart/form-data" data-ajax novalidate class="grid gap-4"
    data-modal-title="{{ __('billing::finance.settle.title') }}" data-modal-subtitle="{{ $tenant->name }}">
    @csrf
    <p class="text-sm text-ink-secondary">{{ __('billing::finance.settle.help', ['owed' => $owed->format(), 'count' => $summary['open_orders']->count()]) }}</p>
    <x-ui::input name="amount" type="number" step="0.01" min="0.01" :max="$owed->toDecimal()" :value="$owed->toDecimal()" :label="__('billing::finance.settle.amount')" required dir="ltr" />
    <x-ui::select name="method" :label="__('billing::finance.settle.method')" :options="$methods" selected="bank_transfer" :search="false" required />
    <x-ui::input name="reference" :label="__('billing::finance.settle.reference')" maxlength="120" :helper="__('billing::finance.settle.reference_help')" dir="ltr" />
    <x-ui::file-upload name="attachment" :label="__('billing::finance.settle.attachment')" accept=".pdf,.png,.jpg,.jpeg,.zip" :hint="__('billing::finance.settle.attachment_hint')" />
    <x-ui::textarea name="note" :label="__('billing::finance.settle.note')" rows="2" maxlength="1000" />
    @include('commerce::partials.modal-actions', ['submit' => __('billing::finance.save'), 'cancel' => __('billing::finance.cancel')])
</form>
