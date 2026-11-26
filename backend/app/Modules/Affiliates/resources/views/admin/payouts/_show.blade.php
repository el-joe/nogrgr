{{-- ADM-062 payout request detail (closed request or read-only admin): $payoutRequest (affiliate, payout). --}}
@php
    $locale = app()->getLocale();
@endphp
<div class="grid gap-4" data-modal-title="{{ __('affiliates::admin.payouts.detail_title', ['reference' => $payoutRequest->reference()]) }}" data-modal-subtitle="{{ $payoutRequest->affiliate->name ?? '' }}">
    <dl class="grid gap-3 text-sm">
        <div class="flex items-center justify-between gap-4"><dt class="text-ink-secondary">{{ __('affiliates::admin.payouts.columns.status') }}</dt><dd>@include('affiliates::admin.partials.badge', ['tone' => $payoutRequest->status->tone(), 'label' => $payoutRequest->status->label()])</dd></div>
        <div class="flex items-center justify-between gap-4"><dt class="text-ink-secondary">{{ __('affiliates::admin.payouts.columns.amount') }}</dt><dd class="font-semibold text-ink" dir="ltr">{{ $payoutRequest->money()->format() }}</dd></div>
        <div class="flex items-center justify-between gap-4"><dt class="text-ink-secondary">{{ __('affiliates::admin.payouts.requested_at') }}</dt><dd class="text-ink">{{ $payoutRequest->created_at?->locale($locale)->translatedFormat('j M Y H:i') }}</dd></div>
        <div class="flex items-center justify-between gap-4"><dt class="text-ink-secondary">{{ __('affiliates::admin.accounts.columns.balance') }}</dt><dd class="text-ink" dir="ltr">{{ $payoutRequest->affiliate?->balanceMoney()->format() ?? '—' }}</dd></div>
        @if ($payoutRequest->processed_at)
            <div class="flex items-center justify-between gap-4"><dt class="text-ink-secondary">{{ __('affiliates::admin.payouts.processed_at') }}</dt><dd class="text-ink">{{ $payoutRequest->processed_at->locale($locale)->translatedFormat('j M Y H:i') }}</dd></div>
        @endif
        @if ($payoutRequest->payout)
            <div class="flex items-center justify-between gap-4"><dt class="text-ink-secondary">{{ __('affiliates::admin.payout.reference') }}</dt><dd class="text-ink" dir="ltr">{{ $payoutRequest->payout->reference ?? $payoutRequest->payout->displayReference() }}</dd></div>
        @endif
        @if ($payoutRequest->note)
            <div class="grid gap-1"><dt class="text-ink-secondary">{{ __('affiliates::admin.payouts.affiliate_note') }}</dt><dd class="whitespace-pre-line text-ink">{{ $payoutRequest->note }}</dd></div>
        @endif
        @if ($payoutRequest->admin_note)
            <div class="grid gap-1"><dt class="text-ink-secondary">{{ __('affiliates::admin.fields.note') }}</dt><dd class="whitespace-pre-line text-ink">{{ $payoutRequest->admin_note }}</dd></div>
        @endif
    </dl>
    <div class="-mx-5 -mb-5 mt-2 flex justify-end gap-2 border-t border-line bg-subtle px-5 py-4 sm:-mx-6 sm:-mb-6 sm:px-6">
        <x-ui::button variant="secondary" data-overlay-close>{{ __('affiliates::admin.close') }}</x-ui::button>
    </div>
</div>
