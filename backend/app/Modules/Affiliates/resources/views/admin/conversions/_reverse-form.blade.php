{{-- ADM-061 "Reverse conversion CV-…" (Figma 74:68877): reason (required). --}}
<form id="conversion-reverse-form" method="POST" action="{{ route('admin.affiliates.conversions.reverse', $conversion->id) }}" data-ajax class="grid gap-4"
    data-modal-title="{{ __('affiliates::admin.conversions.reverse_title', ['reference' => $conversion->reference()]) }}">
    @csrf
    <p class="text-sm text-ink-secondary">{{ __('affiliates::admin.conversions.reverse_help', ['amount' => $conversion->commission()->format()]) }}</p>
    <x-ui::textarea name="reason" :label="__('affiliates::admin.conversions.reason')" rows="4" maxlength="1000" required />
    @include('commerce::partials.modal-actions', ['submit' => __('affiliates::admin.conversions.reverse'), 'cancel' => __('affiliates::admin.cancel'), 'tone' => 'danger', 'icon' => 'return'])
</form>
