{{-- ADM-060 row actions: view, edit (ADM-060a), pay out (ADM-060b). --}}
<div class="flex items-center justify-end gap-1">
    <x-ui::icon-button icon="eye" size="sm" :label="__('affiliates::admin.accounts.view')" data-remote-modal="affiliate-modal" :data-url="route('admin.affiliates.show', $affiliate->id)" />
    @if ($canManage)
        <x-ui::icon-button icon="edit" size="sm" :label="__('affiliates::admin.accounts.edit')" data-remote-modal="affiliate-modal" :data-url="route('admin.affiliates.edit', $affiliate->id)" />
    @endif
    @if ($canPay && $affiliate->balance > 0)
        <x-ui::row-actions>
            <x-ui::dropdown-item icon="wallet" data-remote-modal="affiliate-modal" :data-url="route('admin.affiliates.payout.create', $affiliate->id)">{{ __('affiliates::admin.payout.button') }}</x-ui::dropdown-item>
        </x-ui::row-actions>
    @endif
</div>
