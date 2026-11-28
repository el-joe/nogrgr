{{--
    Admin › Sales › Orders › detail (ADM-021, Figma Admin Panel 49:17280): header with
    status, print label / receipt / cancel, progress steps, items, payment summary with the
    revenue split, shipping & tracking (reassign carrier, timeline), customer, address,
    store, payment (refund, payment log), internal notes. Modals (Figma 70:41386): cancel
    2-step (021a), refund (021b), create return (021c), reassign company (021d).
    Own-products orders are read-only for the platform.
--}}
@php
    $admin = auth('admin')->user();
    $own = $order->isOwnProducts();
    $canManage = ! $own && $admin?->can('orders.manage');
    $address = $order->shipping_address ?? [];
    $sa = fn (string $key) => is_string($address[$key] ?? null) ? $address[$key] : null;
    $country = ($address['country_name'][app()->getLocale()] ?? $address['country_name']['en'] ?? null);
    $total = max(1, $order->grand_total);
    $vendorPct = round($figures->vendorShare->minor * 100 / $total);
    $platformPct = round($figures->platformShare->minor * 100 / $total);
    $title = __('commerce::orders_admin.detail_title', ['number' => $order->number]);
@endphp
<x-layouts.panel surface="admin" :title="$title" data-page="admin-orders">
    <div class="grid gap-6">
        <x-ui::page-header
            :title="$title"
            :subtitle="__('commerce::orders_admin.detail_subtitle', ['date' => $order->placed_at?->translatedFormat('j M Y, H:i'), 'store' => $context->storeName, 'country' => $country])"
            :breadcrumbs="[__('navigation.sections.sales') => null, __('commerce::orders_admin.title') => route('admin.orders.index'), '#'.$order->number => null]"
        >
            <x-slot:actions>
                @include('commerce::partials.status-badge', ['value' => $order->status])
                @if (! $own && $admin?->can('shipping-queue.view'))
                    <x-ui::button variant="secondary" icon="printer" :href="route('admin.shipping-queue.label', $fact->id)" target="_blank">{{ __('commerce::orders_admin.print_label') }}</x-ui::button>
                @endif
                <x-ui::button variant="secondary" icon="file" :href="route('admin.orders.receipt', $fact->id)" target="_blank">{{ __('commerce::orders_admin.receipt') }}</x-ui::button>
                @if ($canManage && $canCancel)
                    <x-ui::button variant="danger" icon="x-circle" data-remote-modal="order-modal" :data-url="route('admin.orders.cancel.form', $fact->id)">{{ __('commerce::orders_admin.cancel.button') }}</x-ui::button>
                @endif
            </x-slot:actions>
        </x-ui::page-header>

        @if ($own)
            <div class="flex items-start gap-3 rounded-card border border-info/30 bg-info-bg px-4 py-3 text-sm text-info" role="note">
                <x-ui::icon name="info" :size="18" class="mt-0.5 shrink-0" />
                <p>{{ __('commerce::orders_admin.own_read_only') }}</p>
            </div>
        @endif

        @include('commerce::partials.order-steps', ['order' => $order])

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
            <div class="grid content-start gap-6">
                <x-ui::card>
                    <div class="mb-3 flex items-center justify-between gap-3">
                        <h2 class="font-semibold text-ink">{{ __('commerce::orders_admin.items', ['count' => $order->items->count()]) }}</h2>
                        @if ($order->fulfilment_branch_id)
                            <x-ui::badge tone="neutral">{{ __('commerce::orders_admin.branch', ['branch' => app(\App\Modules\Commerce\Services\Orders\AdminOrderListService::class)->branchOptions()[$order->fulfilment_branch_id] ?? '—']) }}</x-ui::badge>
                        @endif
                    </div>
                    @include('commerce::partials.items', ['order' => $order])
                </x-ui::card>

                <x-ui::card>
                    <div class="mb-4 flex items-center justify-between gap-3">
                        <h2 class="font-semibold text-ink">{{ __('commerce::orders_admin.payment_summary') }}</h2>
                        @include('commerce::partials.payment-badge', ['order' => $order, 'refunded' => $figures->refunded])
                    </div>
                    @include('commerce::partials.totals', ['order' => $order])
                    <div class="mt-4 rounded-control bg-subtle p-3">
                        <p class="mb-2 text-xs font-medium text-ink-secondary">{{ __('commerce::orders_admin.revenue_split') }}</p>
                        @if ($figures->showsPercentages())
                        <div class="flex h-2 overflow-hidden rounded-full bg-line" role="img" aria-label="{{ __('commerce::orders_admin.revenue_split') }}">
                            <span class="vnd-split-vendor h-full bg-ink" data-width="{{ $vendorPct }}"></span>
                            <span class="vnd-split-platform h-full bg-primary" data-width="{{ $platformPct }}"></span>
                        </div>
                        @endif
                        <ul class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-ink-secondary">
                            <li><span class="me-1 inline-block size-2 rounded-full bg-ink"></span>{{ __($figures->showsPercentages() ? 'commerce::orders_admin.split_vendor' : 'commerce::orders_admin.split_vendor_plain', ['amount' => $figures->vendorShare->format(), 'percent' => $vendorPct]) }}</li>
                            <li><span class="me-1 inline-block size-2 rounded-full bg-primary"></span>{{ __($figures->showsPercentages() ? 'commerce::orders_admin.split_platform' : 'commerce::orders_admin.split_platform_plain', ['amount' => $figures->platformShare->format(), 'percent' => $platformPct]) }}</li>
                        </ul>
                    </div>
                </x-ui::card>

                <x-ui::card>
                    <div class="mb-4 flex items-center justify-between gap-3">
                        <h2 class="font-semibold text-ink">{{ __('commerce::orders_admin.shipping_tracking') }}</h2>
                        @if ($canManage && ! in_array($order->status->value, ['delivered', 'completed', 'cancelled', 'refunded', 'returned'], true))
                            <x-ui::button variant="secondary" size="sm" icon="refresh" data-remote-modal="order-modal" :data-url="route('admin.orders.company.form', $fact->id)">{{ __('commerce::fulfilment.reassign.button') }}</x-ui::button>
                        @endif
                    </div>
                    <dl class="mb-5 grid gap-4 text-sm sm:grid-cols-3">
                        <div><dt class="text-xs text-ink-secondary">{{ __('commerce::fulfilment.fields.company') }}</dt><dd class="font-semibold text-ink">{{ $order->shipping_company_name ?? '—' }}</dd></div>
                        <div><dt class="text-xs text-ink-secondary">{{ __('commerce::fulfilment.fields.tracking') }}</dt><dd class="font-semibold text-ink" dir="ltr">{{ $order->tracking_number ?? '—' }}</dd></div>
                        <div><dt class="text-xs text-ink-secondary">{{ __('commerce::fulfilment.fields.estimate') }}</dt><dd class="font-semibold text-ink">{{ $order->delivery_min_date && ! $order->isShipmentCancelled() ? $order->delivery_min_date->translatedFormat('j M').' – '.$order->delivery_max_date?->translatedFormat('j M') : '—' }}</dd></div>
                    </dl>
                    @include('commerce::partials.tracking', ['order' => $order])
                </x-ui::card>
            </div>

            <div class="grid content-start gap-6">
                <x-ui::card>
                    <h2 class="mb-3 font-semibold text-ink">{{ __('commerce::orders_admin.customer') }}</h2>
                    <div class="flex items-center gap-3">
                        <x-ui::avatar :name="(string) $order->customer_name" :size="40" />
                        <div class="min-w-0">
                            <p class="truncate font-medium text-ink">{{ $order->customer_name ?? '—' }}</p>
                            <p class="text-xs text-ink-secondary">{{ trans_choice('commerce::orders_admin.nth_order', $customerOrders, ['count' => $customerOrders]) }}</p>
                        </div>
                    </div>
                    <ul class="mt-3 grid gap-2 text-sm text-ink-secondary">
                        @if ($order->customer_email)<li class="flex items-center gap-2"><x-ui::icon name="mail" :size="16" /><span dir="ltr">{{ $order->customer_email }}</span></li>@endif
                        @if ($order->customer_phone)<li class="flex items-center gap-2"><x-ui::icon name="message" :size="16" /><span dir="ltr">{{ $order->customer_phone }}</span></li>@endif
                    </ul>
                </x-ui::card>

                <x-ui::card>
                    <h2 class="mb-3 font-semibold text-ink">{{ __('commerce::orders_admin.shipping_address') }}</h2>
                    @if ($address === [])
                        <p class="text-sm text-ink-secondary">{{ __('commerce::orders_admin.no_address') }}</p>
                    @else
                        <address class="grid gap-0.5 text-sm not-italic text-ink">
                            <span>{{ $sa('full_name') }}</span>
                            <span>{{ $sa('address_line1') }}</span>
                            <span>{{ collect([$sa('city'), $sa('state'), $country])->filter()->implode(', ') }}</span>
                            @if ($sa('phone'))<span dir="ltr">{{ $sa('phone') }}</span>@endif
                        </address>
                    @endif
                </x-ui::card>

                <x-ui::card>
                    <div class="mb-3 flex items-center justify-between"><h2 class="font-semibold text-ink">{{ __('commerce::orders_admin.vendor') }}</h2></div>
                    <dl class="grid gap-2 text-sm">
                        <div class="flex justify-between gap-4"><dt class="text-ink-secondary">{{ __('commerce::orders_admin.store') }}</dt><dd class="font-medium text-ink">{{ $context->storeName }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-ink-secondary">{{ __('commerce::orders_admin.order_kind') }}</dt><dd class="font-medium text-ink">{{ $order->kind->label() }}</dd></div>
                    </dl>
                    <div class="mt-4 border-t border-line pt-4">@include('commerce::partials.settlement', ['figures' => $figures, 'order' => $order])</div>
                </x-ui::card>

                <x-ui::card>
                    <div class="mb-3 flex items-center justify-between gap-3">
                        <h2 class="font-semibold text-ink">{{ __('commerce::orders_admin.payment') }}</h2>
                        @include('commerce::partials.payment-badge', ['order' => $order, 'refunded' => $figures->refunded])
                    </div>
                    <dl class="grid gap-2 text-sm">
                        <div class="flex justify-between gap-4"><dt class="text-ink-secondary">{{ __('commerce::orders_admin.gateway') }}</dt><dd class="font-medium text-ink">{{ \App\Modules\Commerce\DataTables\VendorOrdersDataTable::gatewayLabel($order->payment_gateway) }}{{ $order->payment_source ? ' · '.$order->payment_source->label() : '' }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-ink-secondary">{{ __('commerce::orders_admin.transaction') }}</dt><dd class="max-w-40 truncate font-medium text-ink" dir="ltr">{{ $payment?->transaction_id ?? '—' }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-ink-secondary">{{ __('commerce::orders_admin.captured') }}</dt><dd class="font-medium text-ink">{{ $order->paid_at?->translatedFormat('j M, H:i') ?? '—' }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-ink-secondary">{{ __('commerce::orders_admin.refundable') }}</dt><dd class="font-medium text-ink" dir="ltr">{{ $remaining->format() }}</dd></div>
                    </dl>
                    <div class="mt-4 flex flex-wrap gap-2">
                        @if (! $own && $admin?->can('refunds.manage') && $remaining->isPositive())
                            <x-ui::button variant="secondary" size="sm" icon="return" data-remote-modal="order-modal" :data-url="route('admin.orders.refunds.create', $fact->id)">{{ __('commerce::orders_admin.refund') }}</x-ui::button>
                        @endif
                        @if (! $own && $admin?->can('returns.manage') && in_array($order->status->value, ['shipped', 'delivered', 'completed'], true))
                            <x-ui::button variant="secondary" size="sm" icon="box" data-remote-modal="order-modal" :data-url="route('admin.orders.returns.create', $fact->id)">{{ __('commerce::orders_admin.create_return') }}</x-ui::button>
                        @endif
                    </div>
                    @if ($attempts->isNotEmpty())
                        <details class="mt-4 text-sm">
                            <summary class="cursor-pointer font-medium text-ink-brand">{{ __('commerce::orders_admin.payment_log') }}</summary>
                            <ul class="mt-2 grid gap-2">
                                @foreach ($attempts as $attempt)
                                    <li class="flex items-center justify-between gap-3 rounded-control bg-subtle px-3 py-2">
                                        <span class="min-w-0"><span class="block truncate font-medium text-ink" dir="ltr">{{ $attempt->reference }}</span><span class="text-xs text-ink-secondary">{{ $attempt->created_at?->translatedFormat('j M, H:i') }} · {{ \App\Modules\Commerce\DataTables\VendorOrdersDataTable::gatewayLabel($attempt->gateway) }}</span></span>
                                        @include('commerce::partials.status-badge', ['value' => $attempt->status])
                                    </li>
                                @endforeach
                            </ul>
                        </details>
                    @endif
                </x-ui::card>

                <x-ui::card>
                    <h2 class="mb-3 font-semibold text-ink">{{ __('commerce::orders_admin.notes.title') }}</h2>
                    @if ($admin?->can('orders.manage'))
                        <form method="POST" action="{{ route('admin.orders.notes.store', $fact->id) }}" data-ajax novalidate class="grid gap-2">
                            @csrf
                            <x-ui::textarea name="note" :label="__('commerce::orders_admin.notes.field')" :placeholder="__('commerce::orders_admin.notes.placeholder')" rows="3" maxlength="2000" />
                            <p class="text-xs text-ink-secondary">{{ __('commerce::orders_admin.notes.hint') }}</p>
                            <div><x-ui::button type="submit" variant="secondary" size="sm">{{ __('commerce::orders_admin.notes.add') }}</x-ui::button></div>
                        </form>
                    @endif
                    @if ($notes->isEmpty())
                        <p class="mt-3 text-sm text-ink-secondary">{{ __('commerce::orders_admin.notes.empty') }}</p>
                    @else
                        <ul class="mt-4 grid gap-3">
                            @foreach ($notes as $note)
                                <li class="rounded-control bg-subtle px-3 py-2 text-sm">
                                    <p class="whitespace-pre-line text-ink">{{ $note->note }}</p>
                                    <p class="mt-1 text-xs text-ink-secondary">{{ $note->admin_name }} · {{ $note->created_at?->translatedFormat('j M, H:i') }}</p>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-ui::card>
            </div>
        </div>
    </div>

    <x-ui::modal id="order-modal" :title="$title">
        @include('commerce::admin.partials.remote-states')
    </x-ui::modal>
</x-layouts.panel>
