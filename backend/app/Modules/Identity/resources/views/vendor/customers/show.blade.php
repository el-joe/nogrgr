{{--
    Vendor › Customers › detail (VEN-025b, Figma Vendor Panel 84:105854): name + status,
    Deactivate / Activate and Edit (VEN-025a modal), KPI cards (orders, spent, returns,
    last order), orders & payments (server-side table), profile (e-mail, phone, language,
    newsletter) and addresses (add / edit modal VEN-025c, make default, delete).
--}}
@php
    $active = $customer->status === \App\Modules\Identity\Enums\AccountStatus::Active;
    $languages = \App\Shared\Support\Locale::enabled();
@endphp
<x-vendor::layout :title="$customer->name" page="vendor-customers">
    <div class="grid gap-6">
        <x-ui::page-header :title="$customer->name" :breadcrumbs="[__('identity::customers.title') => vendor_route('sales.customers.index'), $customer->name => null]">
            <x-slot:status>
                @include('identity::vendor.customers._status', ['customer' => $customer])
            </x-slot:status>
            @if ($canManage)
                <x-slot:actions>
                    <x-ui::button
                        variant="secondary"
                        :icon="$active ? 'x' : 'check'"
                        :data-confirm="__($active ? 'identity::customers.deactivate_message' : 'identity::customers.activate_message')"
                        :data-confirm-title="$customer->name"
                        data-confirm-tone="primary"
                        :data-confirm-label="__($active ? 'identity::customers.deactivate' : 'identity::customers.activate')"
                        data-confirm-method="PATCH"
                        :data-confirm-url="vendor_route('sales.customers.toggle-active', ['customer' => $customer->id, 'from_detail' => 1])"
                    >{{ __($active ? 'identity::customers.deactivate' : 'identity::customers.activate') }}</x-ui::button>
                    <x-ui::button
                        variant="secondary"
                        icon="trash"
                        :data-confirm="__('identity::customers.delete_message')"
                        :data-confirm-title="__('identity::customers.delete_title', ['name' => $customer->name])"
                        data-confirm-method="DELETE"
                        :data-confirm-url="vendor_route('sales.customers.destroy', ['customer' => $customer->id, 'from_detail' => 1])"
                    >{{ __('identity::customers.delete') }}</x-ui::button>
                    <x-ui::button icon="edit" data-remote-modal="vendor-customer-modal" :data-url="vendor_route('sales.customers.edit', ['customer' => $customer->id])">{{ __('identity::customers.edit') }}</x-ui::button>
                </x-slot:actions>
            @endif
        </x-ui::page-header>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-ui::kpi-card :label="__('identity::customers.detail.orders')" :value="number_format($summary['orders'])" icon="cart" :comparison="__('identity::customers.detail.orders_hint')" />
            <x-ui::kpi-card :label="__('identity::customers.detail.spent')" :value="$summary['spent']->format()" icon="wallet" :comparison="__('identity::customers.detail.spent_hint')" />
            <x-ui::kpi-card :label="__('identity::customers.detail.returns')" :value="number_format($summary['returns'])" icon="return" :comparison="__('identity::customers.detail.returns_hint')" />
            <x-ui::kpi-card :label="__('identity::customers.detail.last_order')" :value="$summary['last_order_at']?->translatedFormat('d M') ?? '—'" icon="clock" :comparison="$summary['last_order_at'] ? __('identity::customers.detail.last_order_hint') : __('identity::customers.detail.no_orders')" />
        </div>

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
            <div class="grid min-w-0 content-start gap-3">
                <h2 class="text-base font-semibold text-ink">{{ __('identity::customers.detail.orders_payments') }}</h2>
                <x-ui::datatable
                    id="vendor-customer-orders"
                    :url="vendor_route('sales.customers.orders', ['customer' => $customer->id])"
                    :columns="[
                        ['data' => 'order', 'title' => __('identity::customers.detail.order')],
                        ['data' => 'total', 'title' => __('identity::customers.detail.total'), 'align' => 'end'],
                        ['data' => 'payment', 'title' => __('identity::customers.detail.payment')],
                        ['data' => 'status', 'title' => __('identity::customers.detail.status')],
                    ]"
                    :order="[[0, 'desc']]"
                    :empty-title="__('identity::customers.detail.orders_empty_title')"
                    :empty-description="__('identity::customers.detail.orders_empty_description')"
                />
            </div>

            <div class="grid content-start gap-6">
                <x-ui::card :title="__('identity::customers.detail.profile')">
                    <dl class="grid gap-3 text-sm">
                        <div class="flex justify-between gap-4"><dt class="text-ink-secondary">{{ __('identity::customers.detail.email') }}</dt><dd class="truncate font-semibold text-ink" dir="ltr">{{ $customer->email ?? '—' }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-ink-secondary">{{ __('identity::customers.detail.phone') }}</dt><dd class="font-semibold text-ink" dir="ltr">{{ $customer->phone ?? '—' }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-ink-secondary">{{ __('identity::customers.detail.language') }}</dt><dd class="font-semibold text-ink">{{ $customer->locale ? ($languages[$customer->locale]['native'] ?? $customer->locale) : '—' }}</dd></div>
                        <div class="flex items-center justify-between gap-4">
                            <dt class="text-ink-secondary">{{ __('identity::customers.detail.newsletter') }}</dt>
                            <dd>
                                @if ($subscribed)
                                    <x-ui::badge tone="success">{{ __('identity::customers.detail.subscribed') }}</x-ui::badge>
                                @else
                                    <x-ui::badge>{{ __('identity::customers.detail.not_subscribed') }}</x-ui::badge>
                                @endif
                            </dd>
                        </div>
                        <div class="flex justify-between gap-4"><dt class="text-ink-secondary">{{ __('identity::customers.detail.joined') }}</dt><dd class="font-semibold text-ink">{{ $customer->created_at?->translatedFormat('d M Y') }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-ink-secondary">{{ __('identity::customers.detail.last_login') }}</dt><dd class="font-semibold text-ink">{{ $customer->last_login_at?->translatedFormat('d M Y, H:i') ?? __('identity::customers.detail.never') }}</dd></div>
                    </dl>
                </x-ui::card>

                <x-ui::card :title="__('identity::customers.addresses.title')">
                    @if ($canManage)
                        <x-slot:actions>
                            <x-ui::button variant="secondary" size="sm" icon="plus" data-remote-modal="vendor-address-modal" :data-url="vendor_route('sales.customers.addresses.create', ['customer' => $customer->id])">{{ __('identity::customers.addresses.add') }}</x-ui::button>
                        </x-slot:actions>
                    @endif
                    @if ($addresses->isEmpty())
                        <x-ui::empty-state compact icon="map-pin" :description="__('identity::customers.addresses.empty')" />
                    @else
                        <ul class="grid divide-y divide-line">
                            @foreach ($addresses as $address)
                                <li class="flex items-start gap-3 py-3 first:pt-0 last:pb-0">
                                    <span class="grid size-9 shrink-0 place-items-center rounded-control bg-subtle text-ink-secondary"><x-ui::icon name="map-pin" :size="18" /></span>
                                    <div class="grid min-w-0 flex-1 gap-0.5">
                                        <p class="text-sm font-semibold text-ink">{{ collect([$address->label ?? $address->full_name, $address->is_default ? __('identity::customers.addresses.default') : null])->filter()->implode(' · ') }}</p>
                                        <p class="text-xs text-ink-secondary">{{ collect([$address->address_line_1, $address->city, $address->state, $address->country?->name])->filter()->implode(', ') }}</p>
                                        <p class="text-xs text-ink-secondary" dir="ltr">{{ $address->phone }}</p>
                                    </div>
                                    @if ($canManage)
                                        <div class="flex shrink-0 items-center gap-1">
                                            <x-ui::icon-button icon="edit" size="sm" :label="__('identity::customers.addresses.edit')" data-remote-modal="vendor-address-modal" :data-url="vendor_route('sales.customers.addresses.edit', ['customer' => $customer->id, 'address' => $address->id])" />
                                            <x-ui::row-actions>
                                                @unless ($address->is_default)
                                                    <x-ui::dropdown-item icon="check-circle" :data-confirm="__('identity::customers.addresses.make_default')" :data-confirm-title="$address->label ?? $address->full_name" data-confirm-tone="primary" :data-confirm-label="__('identity::customers.addresses.make_default')" data-confirm-method="POST" :data-confirm-url="vendor_route('sales.customers.addresses.default', ['customer' => $customer->id, 'address' => $address->id])">{{ __('identity::customers.addresses.make_default') }}</x-ui::dropdown-item>
                                                @endunless
                                                <x-ui::dropdown-item icon="trash" tone="danger" :data-confirm="__('identity::customers.addresses.delete_message')" :data-confirm-title="__('identity::customers.addresses.delete_title')" data-confirm-method="DELETE" :data-confirm-url="vendor_route('sales.customers.addresses.destroy', ['customer' => $customer->id, 'address' => $address->id])">{{ __('identity::customers.addresses.delete') }}</x-ui::dropdown-item>
                                            </x-ui::row-actions>
                                        </div>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-ui::card>
            </div>
        </div>
    </div>

    @if ($canManage)
        @include('identity::vendor.customers._modal', ['id' => 'vendor-customer-modal', 'title' => __('identity::customers.edit_title', ['name' => $customer->name]), 'form' => 'vendor-customer-form'])
        @include('identity::vendor.customers._modal', ['id' => 'vendor-address-modal', 'title' => __('identity::customers.addresses.create_title'), 'form' => 'vendor-address-form'])
    @endif
</x-vendor::layout>
