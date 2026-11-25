{{-- ADM-021d reassign shipping company (Figma 70:41386): company picker + live new estimate. --}}
<form id="order-company-form" method="POST" action="{{ $action }}" data-ajax class="grid gap-4"
    data-modal-title="{{ __('commerce::fulfilment.reassign.title') }}">
    @csrf
    @method('PUT')
    <x-ui::select name="shipping_company_id" :label="__('commerce::fulfilment.fields.company')" :options="$companies" :selected="$order->shipping_company_id" :placeholder="__('commerce::fulfilment.reassign.placeholder')" required :data-estimate-url="$estimateUrl" />
    <p class="flex items-start gap-2 rounded-control bg-info-bg px-3 py-2 text-sm text-info" data-estimate data-loading-text="{{ __('commerce::fulfilment.reassign.loading') }}" data-error-text="{{ __('commerce::fulfilment.reassign.no_estimate') }}" aria-live="polite">
        {{ $order->delivery_min_date ? __('commerce::fulfilment.reassign.current', ['from' => $order->delivery_min_date->translatedFormat('j M'), 'to' => $order->delivery_max_date?->translatedFormat('j M'), 'company' => $order->shipping_company_name ?? '—']) : __('commerce::fulfilment.reassign.pick') }}
    </p>
    @include('commerce::partials.modal-actions', ['submit' => __('commerce::orders_admin.save')])
</form>
