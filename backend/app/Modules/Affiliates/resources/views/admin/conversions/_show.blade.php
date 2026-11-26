{{-- ADM-061 conversion detail modal: $conversion (affiliate, tenant, promoCode), $canManage. --}}
@php
    $locale = app()->getLocale();
    $money = static fn (int $minor): string => \App\Shared\Money\Money::ofMinor($minor)->format();
    $date = static fn ($value): string => $value?->locale($locale)->translatedFormat('j M Y H:i') ?? '—';
@endphp
<div class="grid gap-4" data-modal-title="{{ __('affiliates::admin.conversions.detail_title', ['reference' => $conversion->reference()]) }}">
    <dl class="grid gap-3 text-sm">
        <div class="flex items-center justify-between gap-4"><dt class="text-ink-secondary">{{ __('affiliates::admin.conversions.columns.status') }}</dt><dd>@include('affiliates::admin.partials.badge', ['tone' => $conversion->status->tone(), 'label' => $conversion->status->label()])</dd></div>
        <div class="flex items-center justify-between gap-4"><dt class="text-ink-secondary">{{ __('affiliates::admin.conversions.columns.affiliate') }}</dt><dd class="font-medium text-ink">{{ $conversion->affiliate->name ?? '—' }}</dd></div>
        <div class="flex items-center justify-between gap-4"><dt class="text-ink-secondary">{{ __('affiliates::admin.conversions.source') }}</dt><dd class="text-ink">{{ $conversion->source->label() }}@if ($conversion->promoCode) <span dir="ltr">({{ $conversion->promoCode->code }})</span>@endif</dd></div>
        <div class="flex items-center justify-between gap-4"><dt class="text-ink-secondary">{{ __('affiliates::admin.conversions.columns.store') }}</dt><dd class="text-ink">{{ $conversion->tenant->name ?? '—' }}</dd></div>
        <div class="flex items-center justify-between gap-4"><dt class="text-ink-secondary">{{ __('affiliates::admin.conversions.fields.sale') }}</dt><dd class="text-ink" dir="ltr">{{ $conversion->sale_amount > 0 ? $money($conversion->sale_amount) : '—' }}</dd></div>
        <div class="flex items-center justify-between gap-4"><dt class="text-ink-secondary">{{ __('affiliates::admin.conversions.fields.rate') }}</dt><dd class="text-ink" dir="ltr">{{ $conversion->rateLabel() }}</dd></div>
        <div class="flex items-center justify-between gap-4"><dt class="text-ink-secondary">{{ __('affiliates::admin.conversions.columns.commission') }}</dt><dd class="font-semibold text-ink" dir="ltr">{{ $money($conversion->commission_amount) }}</dd></div>
        <div class="flex items-center justify-between gap-4"><dt class="text-ink-secondary">{{ __('affiliates::admin.conversions.fields.paid') }}</dt><dd class="text-ink" dir="ltr">{{ $money($conversion->paid_amount) }}</dd></div>
        <div class="flex items-center justify-between gap-4"><dt class="text-ink-secondary">{{ __('affiliates::admin.conversions.fields.signed_up') }}</dt><dd class="text-ink">{{ $date($conversion->created_at) }}</dd></div>
        @if ($conversion->approved_at)
            <div class="flex items-center justify-between gap-4"><dt class="text-ink-secondary">{{ __('affiliates::admin.conversions.fields.approved_at') }}</dt><dd class="text-ink">{{ $date($conversion->approved_at) }}</dd></div>
        @endif
        @if ($conversion->reversed_at)
            <div class="flex items-center justify-between gap-4"><dt class="text-ink-secondary">{{ __('affiliates::admin.conversions.fields.reversed_at') }}</dt><dd class="text-ink">{{ $date($conversion->reversed_at) }}</dd></div>
        @endif
        @if ($conversion->note)
            <div class="grid gap-1"><dt class="text-ink-secondary">{{ __('affiliates::admin.fields.note') }}</dt><dd class="whitespace-pre-line text-ink">{{ $conversion->note }}</dd></div>
        @endif
    </dl>
    <div class="-mx-5 -mb-5 mt-2 flex justify-end gap-2 border-t border-line bg-subtle px-5 py-4 sm:-mx-6 sm:-mb-6 sm:px-6">
        <x-ui::button variant="secondary" data-overlay-close>{{ __('affiliates::admin.close') }}</x-ui::button>
        @if ($canManage && $conversion->status->canReverse())
            <x-ui::button variant="danger" icon="return" data-remote-modal="affiliate-modal" :data-url="route('admin.affiliates.conversions.reverse.form', $conversion->id)">{{ __('affiliates::admin.conversions.reverse') }}</x-ui::button>
        @endif
    </div>
</div>
