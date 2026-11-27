{{--
    Vendor › Orders › detail (VEN-021, Figma 84:102416): print invoice, refund (VEN-021b),
    cancel (VEN-021a), progress, items + totals, shipping (own-products orders: update
    status VEN-021c; central-product orders are shipped by the platform), settlement
    figures with "Pay platform" (VEN-021d), customer.
--}}
@php
    $staff = auth('vendor')->user();
    $own = $order->isOwnProducts();
    $address = $order->shipping_address ?? [];
    $country = $address['country_name'][app()->getLocale()] ?? $address['country_name']['en'] ?? null;
    $title = __('commerce::orders_admin.detail_title', ['number' => $order->number]);
    $canRefund = $staff?->can('refunds.manage') && $order->collectedBy() === \App\Modules\Commerce\Enums\CollectedBy::Vendor && $remaining->isPositive();
@endphp
<x-vendor::layout :title="$title" page="vendor-orders">
    <div class="grid gap-6">
        <x-ui::page-header
            :title="$title"
            :subtitle="collect([$order->placed_at?->translatedFormat('j M Y, H:i'), $order->customer_name, collect([$address['city'] ?? null, $country])->filter()->implode(', '), $order->payment_status->label()])->filter()->implode(' · ')"
            :breadcrumbs="[__('navigation.vendor.orders') => vendor_route('sales.orders.index'), '#'.$order->number => null]"
        >
            <x-slot:actions>
                @include('commerce::partials.status-badge', ['value' => $order->status])
                <x-ui::button variant="secondary" icon="printer" :href="vendor_route('sales.orders.invoice', ['order' => $order->uuid])">{{ __('commerce::vendor_orders.print_invoice') }}</x-ui::button>
                @if ($canRefund)
                    <x-ui::button variant="secondary" icon="return" data-remote-modal="vendor-order-modal" :data-url="vendor_route('sales.orders.refund.create', ['order' => $order->uuid])">{{ __('commerce::vendor_orders.refund') }}</x-ui::button>
                @endif
                @if ($staff?->can('orders.manage') && $canCancel)
                    <x-ui::button variant="danger" icon="x" data-remote-modal="vendor-order-modal" :data-url="vendor_route('sales.orders.cancel.form', ['order' => $order->uuid])">{{ __('commerce::vendor_orders.cancel') }}</x-ui::button>
                @endif
            </x-slot:actions>
        </x-ui::page-header>

        @include('commerce::partials.order-steps', ['order' => $order])

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
            <div class="grid content-start gap-6">
                <x-ui::card>
                    <h2 class="ui-card-title mb-3 font-semibold text-ink">{{ __('commerce::orders_admin.items', ['count' => $order->items->count()]) }}</h2>
                    @include('commerce::partials.items', ['order' => $order])
                    <div class="mt-4 border-t border-line pt-4">@include('commerce::partials.totals', ['order' => $order])</div>
                </x-ui::card>

                <x-ui::card>
                    <div class="mb-4 flex items-center justify-between gap-3">
                        <h2 class="ui-card-title font-semibold text-ink">{{ __('commerce::vendor_orders.shipping') }}</h2>
                        @if ($own && $staff?->can('orders.manage') && $order->fulfilment_status->transitions() !== [] && in_array($order->status->value, ['paid', 'processing', 'shipped'], true))
                            <x-ui::button variant="secondary" size="sm" icon="truck" data-remote-modal="vendor-order-modal" :data-url="vendor_route('sales.orders.shipping.form', ['order' => $order->uuid])">{{ __('commerce::fulfilment.update_status') }}</x-ui::button>
                        @endif
                    </div>
                    <dl class="grid gap-2 text-sm">
                        <div class="flex justify-between gap-4"><dt class="text-ink-secondary">{{ __('commerce::fulfilment.fields.company') }}</dt><dd class="font-semibold text-ink">{{ $order->shipping_company_name ?? '—' }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-ink-secondary">{{ __('commerce::fulfilment.fields.status') }}</dt><dd>@include('commerce::partials.status-badge', ['value' => $order->fulfilment_status])</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-ink-secondary">{{ __('commerce::fulfilment.fields.tracking') }}</dt><dd class="font-semibold text-ink" dir="ltr">{{ $order->tracking_number ?? '—' }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-ink-secondary">{{ __('commerce::fulfilment.fields.estimate') }}</dt><dd class="font-semibold text-ink">{{ $order->deliveryWindowLabel() ?? '—' }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-ink-secondary">{{ __('commerce::vendor_orders.fulfilled_by') }}</dt><dd class="font-semibold text-ink">{{ $own ? tenant('name') : __('commerce::vendor_orders.platform_fulfilled') }}</dd></div>
                    </dl>
                    @unless ($own)
                        <p class="mt-4 flex items-start gap-2 rounded-control bg-info-bg px-3 py-2 text-sm text-info" role="note">
                            <x-ui::icon name="info" :size="16" class="mt-0.5 shrink-0" />
                            <span>{{ __('commerce::vendor_orders.central_note') }}</span>
                        </p>
                    @endunless
                    <div class="mt-5">@include('commerce::partials.tracking', ['order' => $order])</div>
                </x-ui::card>
            </div>

            <div class="grid content-start gap-6">
                <x-ui::card>
                    <h2 class="ui-card-title font-semibold text-ink">{{ __('commerce::settlement.title') }}</h2>
                    <p class="mb-4 text-xs text-ink-secondary">{{ __('commerce::settlement.subtitle') }}</p>
                    @include('commerce::partials.settlement', ['figures' => $figures, 'order' => $order])
                    @if ($figures->owedToPlatform->isPositive() && $staff?->can('orders.manage'))
                        <x-ui::button class="mt-4" block icon="credit-card" :href="vendor_route('finance.settle.show', ['order' => $order->uuid])">{{ __('commerce::settlement.pay', ['amount' => $figures->owedToPlatform->format()]) }}</x-ui::button>
                    @endif
                    @if ($settlements->isNotEmpty())
                        <ul class="mt-4 grid gap-2 border-t border-line pt-4 text-sm">
                            @foreach ($settlements as $settlement)
                                <li class="flex items-center justify-between gap-3">
                                    <span class="text-ink-secondary">{{ $settlement->created_at?->translatedFormat('j M, H:i') }} · <span dir="ltr">{{ $settlement->money()->format() }}</span></span>
                                    @include('commerce::partials.status-badge', ['value' => $settlement->status])
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-ui::card>

                <x-ui::card>
                    <h2 class="ui-card-title mb-3 font-semibold text-ink">{{ __('commerce::orders_admin.customer') }}</h2>
                    <div class="flex items-center gap-3">
                        <x-ui::avatar :name="(string) $order->customer_name" :size="40" />
                        <div class="min-w-0">
                            <p class="truncate font-medium text-ink">{{ $order->customer_name ?? '—' }}</p>
                            <p class="truncate text-xs text-ink-secondary"><span dir="ltr">{{ $order->customer_email }}</span> · {{ trans_choice('commerce::orders_admin.nth_order', $customerOrders, ['count' => $customerOrders]) }}</p>
                        </div>
                    </div>
                    @if ($address !== [])
                        <address class="mt-4 grid gap-0.5 border-t border-line pt-4 text-sm not-italic text-ink-secondary">
                            <span>{{ $address['address_line1'] ?? '' }}</span>
                            <span>{{ collect([$address['city'] ?? null, $country])->filter()->implode(', ') }}</span>
                            <span dir="ltr">{{ $address['phone'] ?? '' }}</span>
                        </address>
                    @endif
                </x-ui::card>
            </div>
        </div>
    </div>

    <x-ui::modal id="vendor-order-modal" :title="$title">
        @include('commerce::admin.partials.remote-states')
    </x-ui::modal>
</x-vendor::layout>
