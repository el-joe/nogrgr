{{-- ADM-053 transaction detail modal (Figma 74:68877 "Transaction TX-…"). --}}
@php
    $lines = $transaction->entries;
    $summary = $lines->map(fn ($line) => ($line->debit > 0 ? __('billing::finance.transactions.debit') : __('billing::finance.transactions.credit')).' '.$line->account->label())->implode(', ');
@endphp
<div class="grid gap-4" data-modal-title="{{ __('billing::finance.transactions.detail_title', ['reference' => $transaction->reference]) }}">
    <dl class="grid gap-3 text-sm">
        <div class="flex items-center justify-between gap-4"><dt class="text-ink-secondary">{{ __('billing::finance.transactions.columns.direction') }}</dt><dd>@include('billing::finance._badge', ['tone' => $transaction->direction->tone(), 'label' => $transaction->direction->label()])</dd></div>
        <div class="flex items-center justify-between gap-4"><dt class="text-ink-secondary">{{ __('billing::finance.transactions.columns.source') }}</dt><dd class="text-end font-medium text-ink">{{ \App\Modules\Billing\Support\LedgerPresenter::title($transaction) }}</dd></div>
        <div class="flex items-center justify-between gap-4"><dt class="text-ink-secondary">{{ __('billing::finance.transactions.columns.store') }}</dt><dd class="text-end font-medium text-ink">{{ $transaction->tenant?->getAttribute('name') ?? '—' }}</dd></div>
        <div class="flex items-center justify-between gap-4"><dt class="text-ink-secondary">{{ __('billing::finance.transactions.columns.amount') }}</dt><dd class="font-semibold text-ink" dir="ltr">{{ $transaction->money()->format() }}</dd></div>
        <div class="flex items-center justify-between gap-4"><dt class="text-ink-secondary">{{ __('billing::finance.transactions.columns.date') }}</dt><dd class="text-ink" dir="ltr">{{ $transaction->occurred_at->locale(app()->getLocale())->translatedFormat('j M Y H:i') }}</dd></div>
        <div class="flex items-center justify-between gap-4"><dt class="text-ink-secondary">{{ __('billing::finance.transactions.entries') }}</dt><dd class="text-end text-ink">{{ trans_choice('billing::finance.transactions.entries_count', $lines->count(), ['count' => $lines->count()]) }} ({{ $summary }})</dd></div>
    </dl>
    <div class="overflow-x-auto rounded-control border border-line">
        <table class="w-full text-sm">
            <thead class="bg-subtle text-ink-secondary">
                <tr>
                    <th class="px-3 py-2 text-start font-medium">{{ __('billing::finance.transactions.account') }}</th>
                    <th class="px-3 py-2 text-end font-medium">{{ __('billing::finance.transactions.debit') }}</th>
                    <th class="px-3 py-2 text-end font-medium">{{ __('billing::finance.transactions.credit') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @foreach ($lines as $line)
                    <tr>
                        <td class="px-3 py-2 text-ink">{{ $line->account->label() }}@if ($line->order_number)<span class="block text-xs text-ink-secondary" dir="ltr">#{{ $line->order_number }}</span>@endif</td>
                        <td class="px-3 py-2 text-end" dir="ltr">{{ $line->debit > 0 ? \App\Shared\Money\Money::ofMinor($line->debit)->format() : '' }}</td>
                        <td class="px-3 py-2 text-end" dir="ltr">{{ $line->credit > 0 ? \App\Shared\Money\Money::ofMinor($line->credit)->format() : '' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="-mx-5 -mb-5 mt-2 flex justify-end gap-2 border-t border-line bg-subtle px-5 py-4 sm:-mx-6 sm:-mb-6 sm:px-6">
        <x-ui::button variant="secondary" data-overlay-close>{{ __('billing::finance.close') }}</x-ui::button>
    </div>
</div>
