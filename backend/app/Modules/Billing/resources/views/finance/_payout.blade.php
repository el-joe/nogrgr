{{-- ADM-052 payout detail modal: $payout, $editUrl. --}}
<div class="grid gap-4" data-modal-title="{{ __('billing::finance.payouts.detail_title', ['reference' => $payout->reference]) }}">
    <dl class="grid gap-3 text-sm">
        <div class="flex items-center justify-between gap-4"><dt class="text-ink-secondary">{{ __('billing::finance.payouts.fields.store') }}</dt><dd class="font-medium text-ink">{{ $payout->tenant?->getAttribute('name') ?? '—' }}</dd></div>
        <div class="flex items-center justify-between gap-4"><dt class="text-ink-secondary">{{ __('billing::finance.payouts.fields.status') }}</dt><dd>@include('billing::finance._badge', ['tone' => $payout->status->tone(), 'label' => $payout->status->label()])</dd></div>
        <div class="flex items-center justify-between gap-4"><dt class="text-ink-secondary">{{ __('billing::finance.payouts.fields.amount') }}</dt><dd class="font-semibold text-ink" dir="ltr">{{ $payout->money()->format() }}</dd></div>
        <div class="flex items-center justify-between gap-4"><dt class="text-ink-secondary">{{ __('billing::finance.payouts.fields.method') }}</dt><dd class="text-ink">{{ $payout->method->label() }}</dd></div>
        <div class="flex items-center justify-between gap-4"><dt class="text-ink-secondary">{{ __('billing::finance.payouts.fields.scheduled_for') }}</dt><dd class="text-ink" dir="ltr">{{ $payout->scheduled_for?->locale(app()->getLocale())->translatedFormat('j M Y') ?? '—' }}</dd></div>
        <div class="flex items-center justify-between gap-4"><dt class="text-ink-secondary">{{ __('billing::finance.payouts.fields.reference') }}</dt><dd class="text-ink" dir="ltr">{{ $payout->transaction_reference ?? '—' }}</dd></div>
        @if ($payout->paid_at)
            <div class="flex items-center justify-between gap-4"><dt class="text-ink-secondary">{{ __('billing::finance.payouts.paid_at') }}</dt><dd class="text-ink" dir="ltr">{{ $payout->paid_at->locale(app()->getLocale())->translatedFormat('j M Y H:i') }}</dd></div>
        @endif
        @if ($payout->payee)
            <div class="flex items-center justify-between gap-4"><dt class="text-ink-secondary">{{ __('billing::finance.payouts.payee.bank') }}</dt><dd class="text-end text-ink">{{ $payout->payee['bank'] ?? '—' }} <span class="block text-xs text-ink-secondary" dir="ltr">{{ $payout->payee['iban'] ?? '' }}</span></dd></div>
        @endif
        @if ($payout->created_by_name)
            <div class="flex items-center justify-between gap-4"><dt class="text-ink-secondary">{{ __('billing::finance.payouts.created_by') }}</dt><dd class="text-ink">{{ $payout->created_by_name }}</dd></div>
        @endif
        @if ($payout->note)
            <div class="grid gap-1"><dt class="text-ink-secondary">{{ __('billing::finance.payouts.fields.note') }}</dt><dd class="whitespace-pre-line text-ink">{{ $payout->note }}</dd></div>
        @endif
    </dl>
    <div class="-mx-5 -mb-5 mt-2 flex justify-end gap-2 border-t border-line bg-subtle px-5 py-4 sm:-mx-6 sm:-mb-6 sm:px-6">
        <x-ui::button variant="secondary" data-overlay-close>{{ __('billing::finance.close') }}</x-ui::button>
        @if ($editUrl)
            <x-ui::button icon="edit" data-remote-modal="payout-modal" :data-url="$editUrl">{{ __('billing::finance.payouts.edit') }}</x-ui::button>
        @endif
    </div>
</div>
