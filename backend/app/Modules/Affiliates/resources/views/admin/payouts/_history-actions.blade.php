{{-- Payout history row: proof download when attached. --}}
<div class="flex items-center justify-end gap-1">
    @if ($payout->attachment)
        <x-ui::icon-button icon="download" size="sm" :label="__('affiliates::admin.payouts.download_proof')" :href="route('admin.affiliates.payouts.attachment', $payout->id)" />
    @endif
</div>
