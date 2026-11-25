{{-- ADM-051 / VEN-063 settlement detail: the orders one payment covered, method, reference, proof. $rows, $attachmentUrl. --}}
@php
    $first = $rows->first();
    $total = \App\Shared\Money\Money::ofMinor((int) $rows->sum('amount'));
@endphp
<div class="grid gap-4" data-modal-title="{{ __('billing::finance.settlements.detail_title', ['reference' => $first->reference]) }}">
    <dl class="grid gap-3 text-sm">
        @if ($first->relationLoaded('tenant') && $first->tenant)
            <div class="flex items-center justify-between gap-4"><dt class="text-ink-secondary">{{ __('billing::finance.settlements.columns.store') }}</dt><dd class="font-medium text-ink">{{ $first->tenant->getAttribute('name') }}</dd></div>
        @endif
        <div class="flex items-center justify-between gap-4"><dt class="text-ink-secondary">{{ __('billing::finance.settlements.columns.status') }}</dt><dd>@include('commerce::partials.status-badge', ['value' => $first->status])</dd></div>
        <div class="flex items-center justify-between gap-4"><dt class="text-ink-secondary">{{ __('billing::finance.settlements.columns.gateway') }}</dt><dd class="text-ink">{{ \App\Modules\Billing\DataTables\Finance\SettlementsDataTable::gatewayLabel($first->gateway, $first->method) }}</dd></div>
        @if ($first->transaction_reference)
            <div class="flex items-center justify-between gap-4"><dt class="text-ink-secondary">{{ __('billing::finance.settle.reference') }}</dt><dd class="text-ink" dir="ltr">{{ $first->transaction_reference }}</dd></div>
        @endif
        <div class="flex items-center justify-between gap-4"><dt class="text-ink-secondary">{{ __('billing::finance.settlements.columns.amount') }}</dt><dd class="font-semibold text-ink" dir="ltr">{{ $total->format() }}</dd></div>
        @if ($first->paid_at)
            <div class="flex items-center justify-between gap-4"><dt class="text-ink-secondary">{{ __('billing::finance.settlements.paid_at') }}</dt><dd class="text-ink" dir="ltr">{{ $first->paid_at->locale(app()->getLocale())->translatedFormat('j M Y H:i') }}</dd></div>
        @endif
        @if ($first->recorded_by_name)
            <div class="flex items-center justify-between gap-4"><dt class="text-ink-secondary">{{ __('billing::finance.settlements.recorded_by') }}</dt><dd class="text-ink">{{ $first->recorded_by_name }}</dd></div>
        @endif
        @if ($first->note)
            <div class="grid gap-1"><dt class="text-ink-secondary">{{ __('billing::finance.settle.note') }}</dt><dd class="whitespace-pre-line text-ink">{{ $first->note }}</dd></div>
        @endif
    </dl>
    <div class="overflow-x-auto rounded-control border border-line">
        <table class="w-full text-sm">
            <thead class="bg-subtle text-ink-secondary">
                <tr>
                    <th class="px-3 py-2 text-start font-medium">{{ __('billing::finance.money_flow.columns.order') }}</th>
                    <th class="px-3 py-2 text-end font-medium">{{ __('billing::finance.settlements.columns.amount') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @foreach ($rows as $row)
                    <tr>
                        <td class="px-3 py-2 text-ink" dir="ltr">#{{ $row->order_number }}</td>
                        <td class="px-3 py-2 text-end font-medium text-ink" dir="ltr">{{ $row->money()->format() }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="-mx-5 -mb-5 mt-2 flex justify-end gap-2 border-t border-line bg-subtle px-5 py-4 sm:-mx-6 sm:-mb-6 sm:px-6">
        @if ($attachmentUrl)
            <x-ui::button variant="secondary" icon="download" :href="$attachmentUrl">{{ __('billing::finance.settlements.proof') }}</x-ui::button>
        @endif
        <x-ui::button variant="secondary" data-overlay-close>{{ __('billing::finance.close') }}</x-ui::button>
    </div>
</div>
