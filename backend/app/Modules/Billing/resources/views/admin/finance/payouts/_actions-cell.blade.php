{{-- ADM-052 row actions: view, edit (status, reference), cancel. --}}
<div class="flex items-center justify-end gap-1">
    <x-ui::icon-button icon="eye" size="sm" :label="__('billing::finance.payouts.view')" data-remote-modal="payout-modal" :data-url="route('admin.finance.payouts.show', $payout->id)" />
    @if ($canManage && $payout->status !== \App\Modules\Billing\Enums\PayoutStatus::Cancelled)
        <x-ui::icon-button icon="edit" size="sm" :label="__('billing::finance.payouts.edit')" data-remote-modal="payout-modal" :data-url="route('admin.finance.payouts.edit', $payout->id)" />
        <x-ui::row-actions>
            <x-ui::dropdown-item icon="x-circle" tone="danger"
                data-confirm-url="{{ route('admin.finance.payouts.cancel', $payout->id) }}"
                data-confirm-method="POST"
                data-confirm-title="{{ __('billing::finance.payouts.cancel_title', ['reference' => $payout->reference]) }}"
                data-confirm="{{ $payout->status === \App\Modules\Billing\Enums\PayoutStatus::Paid ? __('billing::finance.payouts.cancel_paid_message') : __('billing::finance.payouts.cancel_message') }}"
                data-confirm-label="{{ __('billing::finance.payouts.cancel') }}"
                data-confirm-tone="danger">{{ __('billing::finance.payouts.cancel') }}</x-ui::dropdown-item>
        </x-ui::row-actions>
    @endif
</div>
