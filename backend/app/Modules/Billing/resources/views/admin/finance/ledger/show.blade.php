{{--
    Admin › Finance › Ledger › store (ADM-050a, Figma Admin Panel 74:60018): vendor balance,
    owed to platform, net; ledger entries (date, entry, debit, credit, running balance);
    statement PDF (print); mark settlement paid (Figma 74:68877) and create payout.
--}}
@php
    $money = fn (int $minor) => \App\Shared\Money\Money::ofMinor($minor)->format();
@endphp
<x-layouts.panel surface="admin" :title="__('billing::finance.ledger.show_title', ['store' => $tenant->name])" data-page="admin-finance">
    <div class="grid gap-6">
        <x-ui::page-header :title="__('billing::finance.ledger.show_title', ['store' => $tenant->name])"
            :breadcrumbs="[__('navigation.admin.finance') => null, __('billing::finance.ledger.title') => route('admin.finance.ledger.index'), $tenant->name => null]">
            <x-slot:actions>
                <x-ui::button variant="secondary" icon="printer" :href="route('admin.finance.ledger.statement', $tenant->id)" target="_blank" rel="noopener">{{ __('billing::finance.ledger.statement') }}</x-ui::button>
                @if ($canPayout && $summary['available'] > 0)
                    <x-ui::button variant="secondary" icon="wallet" :href="route('admin.finance.payouts.create', ['tenant_id' => $tenant->id])">{{ __('billing::finance.payouts.create') }}</x-ui::button>
                @endif
                @if ($canSettle && $summary['owed'] > 0)
                    <x-ui::button icon="check" data-remote-modal="settle-modal" :data-url="route('admin.finance.ledger.settle.form', $tenant->id)">{{ __('billing::finance.settle.title') }}</x-ui::button>
                @endif
            </x-slot:actions>
        </x-ui::page-header>

        <div class="grid gap-4 sm:grid-cols-3">
            <div class="rounded-card bg-subtle px-4 py-3">
                <p class="text-xs text-ink-secondary">{{ __('billing::finance.ledger.columns.payable') }}</p>
                <p class="mt-1 text-xl font-semibold text-success" dir="ltr">{{ $money($summary['payable']) }}</p>
            </div>
            <div class="rounded-card bg-subtle px-4 py-3">
                <p class="text-xs text-ink-secondary">{{ __('billing::finance.ledger.columns.receivable') }}</p>
                <p class="mt-1 text-xl font-semibold text-warning" dir="ltr">{{ $money($summary['receivable']) }}</p>
            </div>
            <div class="rounded-card bg-subtle px-4 py-3">
                <p class="text-xs text-ink-secondary">{{ __('billing::finance.ledger.net') }}</p>
                <p class="mt-1 text-xl font-semibold text-ink" dir="ltr">{{ $money($summary['net']) }}</p>
            </div>
        </div>

        <x-ui::datatable
            id="ledger-entries"
            :url="route('admin.finance.ledger.entries', $tenant->id)"
            :columns="[
                ['data' => 'date', 'title' => __('billing::finance.ledger.entries.date')],
                ['data' => 'entry', 'title' => __('billing::finance.ledger.entries.entry'), 'orderable' => false],
                ['data' => 'debit', 'title' => __('billing::finance.ledger.entries.debit'), 'align' => 'end', 'orderable' => false],
                ['data' => 'credit', 'title' => __('billing::finance.ledger.entries.credit'), 'align' => 'end', 'orderable' => false],
                ['data' => 'balance', 'title' => __('billing::finance.ledger.entries.balance'), 'align' => 'end'],
            ]"
            :order="[[0, 'desc']]"
            :empty-title="__('billing::finance.ledger.entries.empty_title')"
            :empty-description="__('billing::finance.ledger.entries.empty_description')"
        >
            <x-slot:filters>
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="font-semibold text-ink">{{ __('billing::finance.ledger.entries.title') }}</h2>
                    <x-ui::filters-bar target="ledger-entries" :search-placeholder="__('billing::finance.transactions.search')">
                        <x-ui::select name="source" :label="__('billing::finance.transactions.filters.source')" label-hidden :options="\App\Modules\Billing\Enums\LedgerSource::options()" :placeholder="__('billing::finance.transactions.filters.source')" :search="false" allow-clear data-filter :data-filter-label="__('billing::finance.transactions.filters.source')" class="w-40" />
                    </x-ui::filters-bar>
                </div>
            </x-slot:filters>
        </x-ui::datatable>
    </div>

    <x-ui::modal id="settle-modal" :title="__('billing::finance.settle.title')">
        @include('commerce::admin.partials.remote-states')
    </x-ui::modal>
</x-layouts.panel>
