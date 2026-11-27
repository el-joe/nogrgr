{{--
    Shipping status modal (ADM-022 / 022a, VEN-021c own-products, Figma 84:107236):
    next step, carrier, tracking number, location / note, shipping branch, notify.
--}}
<form id="shipping-status-form" method="POST" action="{{ $action }}" data-ajax novalidate class="grid gap-4"
    data-modal-title="{{ isset($vendor) ? __('commerce::fulfilment.status_modal.vendor_title') : __('commerce::fulfilment.status_modal.title', ['number' => $order->number]) }}">
    @csrf
    @method('PUT')
    @if ($statuses === [])
        <x-ui::empty-state compact icon="check-circle" :title="__('commerce::fulfilment.status_modal.final')" />
    @else
        <x-ui::select name="status" :label="__('commerce::fulfilment.fields.status')" :options="$statuses" :search="false" required />
        <div class="grid gap-4 sm:grid-cols-2">
            <x-ui::select name="shipping_company_id" :label="__('commerce::fulfilment.fields.company')" :options="$companies" :selected="$order->shipping_company_id" allow-clear :placeholder="__('commerce::fulfilment.fields.company')" />
            <x-ui::input name="tracking_number" :label="__('commerce::fulfilment.fields.tracking')" :value="$order->tracking_number" maxlength="64" dir="ltr" />
        </div>
        @if ($branches !== [] && $order->fulfilment_branch_id === null)
            <x-ui::select name="branch_id" :label="__('commerce::fulfilment.fields.branch')" :options="$branches" allow-clear :placeholder="__('commerce::fulfilment.fields.branch_auto')" :helper="__('commerce::fulfilment.fields.branch_help')" />
        @endif
        <x-ui::input name="location" :label="__('commerce::fulfilment.fields.location')" maxlength="160" />
        <x-ui::textarea name="note" :label="__('commerce::fulfilment.fields.note')" rows="2" maxlength="500" />
        <x-ui::switch name="notify" :label="__('commerce::fulfilment.fields.notify')" checked />
    @endif
    @include('commerce::partials.modal-actions', ['submit' => __('commerce::orders_admin.save')])
</form>
