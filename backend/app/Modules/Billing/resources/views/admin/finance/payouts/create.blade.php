{{--
    Admin › Finance › Payouts › Create (ADM-052a, Figma Admin Panel 74:62688): store,
    amount (USD, ≤ available), method, scheduled date, reference, note, "email the vendor";
    side card: payout details on file (KYC bank, IBAN, account name, verified).
--}}
@php
    $available = $summary !== null ? \App\Shared\Money\Money::ofMinor($summary['available']) : null;
    $payee = $summary['payee'] ?? null;
@endphp
<x-layouts.panel surface="admin" :title="__('billing::finance.payouts.create')" data-page="admin-finance">
    <div class="grid gap-6">
        <x-ui::page-header :title="__('billing::finance.payouts.create')"
            :breadcrumbs="[__('navigation.admin.finance') => null, __('billing::finance.payouts.title') => route('admin.finance.payouts.index'), __('billing::finance.payouts.create_crumb') => null]">
            <x-slot:actions>
                <x-ui::button type="submit" form="payout-form" icon="check" :disabled="$tenant === null || $available === null || ! $available->isPositive()">{{ __('billing::finance.payouts.create') }}</x-ui::button>
            </x-slot:actions>
        </x-ui::page-header>

        <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
            <x-ui::card :title="__('billing::finance.payouts.form_title')">
                <form id="payout-form" method="POST" action="{{ route('admin.finance.payouts.store') }}" data-ajax novalidate class="grid max-w-xl gap-4">
                    @csrf
                    <x-ui::select name="tenant_id" :label="__('billing::finance.payouts.fields.store')" :options="$tenant ? [$tenant->id => $tenant->name] : []" :selected="$tenant?->id" :placeholder="__('billing::finance.payouts.choose_store')" :ajax-url="route('admin.finance.payouts.stores')" required data-reload-param="tenant_id" />
                    @if ($tenant === null)
                        <x-ui::empty-state compact icon="store" :title="__('billing::finance.payouts.pick_store_title')" :description="__('billing::finance.payouts.pick_store_description')" />
                    @else
                        <x-ui::input name="amount" type="number" step="0.01" min="0.01" :max="$available->toDecimal()" :value="$available->isPositive() ? $available->toDecimal() : null" :label="__('billing::finance.payouts.fields.amount')" :helper="__('billing::finance.payouts.available', ['amount' => $available->format()])" required dir="ltr" :disabled="! $available->isPositive()" />
                        @unless ($available->isPositive())
                            <p class="rounded-control bg-warning-bg px-3 py-2 text-sm text-warning" role="status">{{ __('billing::finance.payouts.nothing_available') }}</p>
                        @endunless
                        <x-ui::select name="method" :label="__('billing::finance.payouts.fields.method')" :options="$methods" selected="bank_transfer" :search="false" required />
                        <x-ui::date name="scheduled_for" :label="__('billing::finance.payouts.fields.scheduled_for')" :value="now()->toDateString()" :min="now()->toDateString()" required />
                        <x-ui::input name="transaction_reference" :label="__('billing::finance.payouts.fields.reference')" maxlength="120" dir="ltr" />
                        <x-ui::textarea name="note" :label="__('billing::finance.payouts.fields.note')" rows="4" maxlength="2000" />
                        <x-ui::checkbox name="notify_vendor" :label="__('billing::finance.payouts.fields.notify_vendor')" checked />
                    @endif
                </form>
            </x-ui::card>

            <x-ui::card :title="__('billing::finance.payouts.payee.title')">
                @if ($payee)
                    <dl class="grid gap-3 text-sm">
                        <div class="flex justify-between gap-4"><dt class="text-ink-secondary">{{ __('billing::finance.payouts.payee.bank') }}</dt><dd class="font-medium text-ink">{{ $payee['bank'] ?? '—' }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-ink-secondary">IBAN</dt><dd class="font-medium text-ink" dir="ltr">{{ $payee['iban'] ?? '—' }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-ink-secondary">{{ __('billing::finance.payouts.payee.name') }}</dt><dd class="font-medium text-ink">{{ $payee['name'] ?? '—' }}</dd></div>
                        <div class="flex items-center justify-between gap-4"><dt class="text-ink-secondary">{{ __('billing::finance.payouts.payee.verified') }}</dt><dd>@include('billing::finance._badge', ['tone' => $payee['verified'] ? 'success' : 'warning', 'label' => $payee['verified'] ? __('billing::finance.yes') : __('billing::finance.no')])</dd></div>
                    </dl>
                @else
                    <x-ui::empty-state compact icon="credit-card" :description="$tenant ? __('billing::finance.payouts.payee.none') : __('billing::finance.payouts.payee.pick')" />
                @endif
            </x-ui::card>
        </div>
    </div>
</x-layouts.panel>
