{{-- ADM-050a statement (print / save as PDF, print.css; the dialog opens on load): store, balances and every ledger transaction. --}}
@php
    $money = fn (int $minor) => \App\Shared\Money\Money::ofMinor($minor)->format();
@endphp
<x-layouts.panel surface="admin" :title="__('billing::finance.ledger.statement_title', ['store' => $tenant->name])" data-page="admin-finance">
    <div class="flex flex-wrap items-center justify-between gap-3" data-print-hide>
        <x-ui::button variant="secondary" icon="chevron-left" :href="route('admin.finance.ledger.show', $tenant->id)" class="rtl:[&_svg]:rotate-180">{{ __('billing::finance.ledger.back') }}</x-ui::button>
        <x-ui::button icon="printer" data-print>{{ __('billing::finance.ledger.print') }}</x-ui::button>
    </div>
    <section class="mx-auto mt-6 grid w-full max-w-4xl gap-6 rounded-card bg-surface p-6" data-autoprint>
        <header class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold">{{ __('billing::finance.ledger.statement_title', ['store' => $tenant->name]) }}</h1>
                <p class="text-sm text-ink-secondary">{{ __('billing::finance.ledger.statement_date', ['date' => now()->locale(app()->getLocale())->translatedFormat('j M Y H:i')]) }}</p>
            </div>
        </header>
        <dl class="grid gap-4 sm:grid-cols-3">
            <div class="rounded-card border border-line px-4 py-3"><dt class="text-xs text-ink-secondary">{{ __('billing::finance.ledger.columns.payable') }}</dt><dd class="text-lg font-semibold" dir="ltr">{{ $money($summary['payable']) }}</dd></div>
            <div class="rounded-card border border-line px-4 py-3"><dt class="text-xs text-ink-secondary">{{ __('billing::finance.ledger.columns.receivable') }}</dt><dd class="text-lg font-semibold" dir="ltr">{{ $money($summary['receivable']) }}</dd></div>
            <div class="rounded-card border border-line px-4 py-3"><dt class="text-xs text-ink-secondary">{{ __('billing::finance.ledger.net') }}</dt><dd class="text-lg font-semibold" dir="ltr">{{ $money($summary['net']) }}</dd></div>
        </dl>
        @if ($transactions->isEmpty())
            <x-ui::empty-state :title="__('billing::finance.ledger.entries.empty_title')" :description="__('billing::finance.ledger.entries.empty_description')" />
        @else
            <div class="relative min-w-0 overflow-x-auto">
            <table class="w-full min-w-[640px] text-sm">
                <thead class="border-b border-line text-ink-secondary">
                    <tr>
                        <th class="px-2 py-2 text-start font-medium">{{ __('billing::finance.ledger.entries.date') }}</th>
                        <th class="px-2 py-2 text-start font-medium">{{ __('billing::finance.ledger.entries.entry') }}</th>
                        <th class="px-2 py-2 text-end font-medium">{{ __('billing::finance.ledger.entries.debit') }}</th>
                        <th class="px-2 py-2 text-end font-medium">{{ __('billing::finance.ledger.entries.credit') }}</th>
                        <th class="px-2 py-2 text-end font-medium">{{ __('billing::finance.ledger.entries.balance') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach ($transactions as $transaction)
                        <tr>
                            <td class="px-2 py-2" dir="ltr">{{ $transaction->occurred_at->translatedFormat('j M Y') }}</td>
                            <td class="px-2 py-2">{{ \App\Modules\Billing\Support\LedgerPresenter::title($transaction) }}<span class="block text-xs text-ink-secondary">{{ \App\Modules\Billing\Support\LedgerPresenter::subtitle($transaction) }}</span></td>
                            <td class="px-2 py-2 text-end" dir="ltr">{{ $transaction->net_amount < 0 ? $money(-$transaction->net_amount) : '' }}</td>
                            <td class="px-2 py-2 text-end" dir="ltr">{{ $transaction->net_amount > 0 ? $money($transaction->net_amount) : '' }}</td>
                            <td class="px-2 py-2 text-end font-medium" dir="ltr">{{ $money($transaction->balance_after) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
        @endif
    </section>
</x-layouts.panel>
