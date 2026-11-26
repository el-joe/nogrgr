{{-- ADM-062 row action: details (pending + manage = Pay out modal). --}}
<div class="flex items-center justify-end gap-1">
    <x-ui::icon-button icon="eye" size="sm" :label="__('affiliates::admin.payouts.details')" data-remote-modal="affiliate-modal" :data-url="route('admin.affiliates.payouts.show', $request->id)" />
</div>
