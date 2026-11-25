{{-- ADM-050 row actions: view ledger, mark settlement paid, create payout. --}}
<div class="flex items-center justify-end gap-1">
    <x-ui::icon-button icon="eye" size="sm" :href="route('admin.finance.ledger.show', $tenant->id)" :label="__('billing::finance.ledger.view')" />
    @if ($canSettle || $canPayout)
        <x-ui::row-actions>
            @if ($canSettle)
                <x-ui::dropdown-item icon="check" data-remote-modal="settle-modal" :data-url="route('admin.finance.ledger.settle.form', $tenant->id)">{{ __('billing::finance.settle.title') }}</x-ui::dropdown-item>
            @endif
            @if ($canPayout)
                <x-ui::dropdown-item icon="wallet" :href="route('admin.finance.payouts.create', ['tenant_id' => $tenant->id])">{{ __('billing::finance.payouts.create') }}</x-ui::dropdown-item>
            @endif
        </x-ui::row-actions>
    @endif
</div>
