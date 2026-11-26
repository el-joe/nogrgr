{{-- ADM-060 affiliate detail modal: $affiliate (clicks_count, conversions_count), $commission, $referralUrl, $recent conversions. --}}
@php
    $money = static fn (int $minor): string => \App\Shared\Money\Money::ofMinor($minor)->format();
    $locale = app()->getLocale();
    $statusTone = \App\Modules\Affiliates\DataTables\AffiliatesDataTable::statusTone($affiliate->status);
@endphp
<div class="grid gap-5" data-modal-title="{{ $affiliate->name }}" data-modal-subtitle="{{ $affiliate->email ?? __('affiliates::admin.accounts.auto_created') }}">
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        @foreach ([
            'clicks' => number_format((int) $affiliate->clicks_count),
            'conversions' => number_format((int) $affiliate->conversions_count),
            'earned' => $money($affiliate->total_earned),
            'balance' => $money($affiliate->balance),
        ] as $key => $value)
            <div class="rounded-control bg-subtle px-3 py-2">
                <p class="text-xs text-ink-secondary">{{ __('affiliates::admin.accounts.columns.'.$key) }}</p>
                <p class="mt-1 font-semibold text-ink" dir="ltr">{{ $value }}</p>
            </div>
        @endforeach
    </div>
    <dl class="grid gap-3 text-sm">
        <div class="flex items-center justify-between gap-4"><dt class="text-ink-secondary">{{ __('affiliates::admin.accounts.columns.type') }}</dt><dd>@include('affiliates::admin.partials.badge', ['tone' => $affiliate->type->tone(), 'label' => $affiliate->type->label()])</dd></div>
        <div class="flex items-center justify-between gap-4"><dt class="text-ink-secondary">{{ __('affiliates::admin.accounts.columns.status') }}</dt><dd>@include('affiliates::admin.partials.badge', ['tone' => $statusTone, 'label' => $affiliate->status->label()])</dd></div>
        <div class="flex items-center justify-between gap-4"><dt class="text-ink-secondary">{{ __('affiliates::admin.accounts.fields.code') }}</dt><dd class="font-medium text-ink" dir="ltr">{{ $affiliate->code }}</dd></div>
        <div class="flex items-center justify-between gap-4"><dt class="text-ink-secondary">{{ __('affiliates::admin.accounts.fields.referral_link') }}</dt><dd class="min-w-0 truncate text-ink" dir="ltr">{{ $referralUrl }}</dd></div>
        <div class="flex items-center justify-between gap-4"><dt class="text-ink-secondary">{{ __('affiliates::admin.accounts.fields.commission') }}</dt><dd class="text-ink" dir="ltr">{{ $commission }}</dd></div>
        <div class="flex items-center justify-between gap-4"><dt class="text-ink-secondary">{{ __('affiliates::admin.accounts.fields.paid') }}</dt><dd class="text-ink" dir="ltr">{{ $money($affiliate->total_paid) }}</dd></div>
        <div class="flex items-center justify-between gap-4"><dt class="text-ink-secondary">{{ __('affiliates::admin.accounts.fields.payout_method') }}</dt><dd class="text-end text-ink">{{ $affiliate->payout_method?->label() ?? __('affiliates::admin.payouts.no_method') }}
            @if ($affiliate->paypal_email)<span class="block text-xs text-ink-secondary" dir="ltr">{{ $affiliate->paypal_email }}</span>@endif
            @if ($affiliate->bank_iban)<span class="block text-xs text-ink-secondary" dir="ltr">{{ $affiliate->bank_name }} · {{ $affiliate->bank_iban }}</span>@endif
        </dd></div>
        <div class="flex items-center justify-between gap-4"><dt class="text-ink-secondary">{{ __('affiliates::admin.accounts.fields.joined') }}</dt><dd class="text-ink">{{ $affiliate->created_at?->locale($locale)->translatedFormat('j M Y') }}</dd></div>
    </dl>
    <div class="grid gap-2">
        <h3 class="text-sm font-semibold text-ink">{{ __('affiliates::admin.accounts.recent') }}</h3>
        @forelse ($recent as $conversion)
            <div class="flex items-center justify-between gap-3 rounded-control border border-line px-3 py-2 text-sm">
                <span class="min-w-0"><span class="block font-medium text-ink">{{ $conversion->tenant->name ?? '—' }}</span><span class="text-xs text-ink-secondary">{{ $conversion->reference() }} · {{ $conversion->created_at?->locale($locale)->translatedFormat('j M Y') }}</span></span>
                <span class="flex items-center gap-2"><span class="font-semibold text-ink" dir="ltr">{{ $conversion->commission()->format() }}</span>@include('affiliates::admin.partials.badge', ['tone' => $conversion->status->tone(), 'label' => $conversion->status->label()])</span>
            </div>
        @empty
            <x-ui::empty-state compact icon="gift" :description="__('affiliates::admin.accounts.no_conversions')" />
        @endforelse
    </div>
    <div class="-mx-5 -mb-5 mt-2 flex justify-end gap-2 border-t border-line bg-subtle px-5 py-4 sm:-mx-6 sm:-mb-6 sm:px-6">
        <x-ui::button variant="secondary" data-overlay-close>{{ __('affiliates::admin.close') }}</x-ui::button>
        @if (auth('admin')->user()?->can('affiliates.manage'))
            <x-ui::button icon="edit" data-remote-modal="affiliate-modal" :data-url="route('admin.affiliates.edit', $affiliate->id)">{{ __('affiliates::admin.accounts.edit') }}</x-ui::button>
        @endif
    </div>
</div>
