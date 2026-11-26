{{--
    VEN-021d Pay platform (Figma Vendor Panel 84:103244): what this vendor-collected order
    owes the platform (platform share − paid settlements), split into shipping and product
    cost, then a platform gateway → hosted checkout → result page.
--}}
@php $title = __('commerce::settlement.pay_title', ['number' => $order->number]); @endphp
<x-vendor::layout :title="$title" page="vendor-orders">
    <div class="grid gap-6">
        <x-ui::page-header :title="$title" :subtitle="__('commerce::settlement.pay_subtitle')"
            :breadcrumbs="[__('navigation.vendor.orders') => vendor_route('sales.orders.index'), '#'.$order->number => vendor_route('sales.orders.show', ['order' => $order->uuid]), __('commerce::settlement.pay_short') => null]" />

        @if (session('error'))
            <p class="flex items-start gap-2 rounded-control bg-danger-bg px-4 py-3 text-sm text-danger" role="alert"><x-ui::icon name="alert-triangle" :size="18" class="mt-0.5 shrink-0" />{{ session('error') }}</p>
        @endif

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_360px]">
            <x-ui::card>
                <h2 class="mb-4 font-semibold text-ink">{{ __('commerce::settlement.breakdown') }}</h2>
                <dl class="grid gap-2.5 text-sm">
                    <div class="flex justify-between gap-4"><dt class="text-ink-secondary">{{ __('commerce::settlement.shipping_cost') }}</dt><dd class="font-semibold text-ink" dir="ltr">{{ $shipping->format() }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-ink-secondary">{{ __('commerce::settlement.product_cost') }}</dt><dd class="font-semibold text-ink" dir="ltr">{{ $product->format() }}</dd></div>
                    @if ($figures->settlementsPaid->isPositive())
                        <div class="flex justify-between gap-4"><dt class="text-ink-secondary">{{ __('commerce::settlement.paid') }}</dt><dd class="font-semibold text-ink" dir="ltr">{{ $figures->settlementsPaid->format() }}</dd></div>
                    @endif
                    <div class="mt-1 flex justify-between gap-4 border-t border-line pt-3 text-base"><dt class="font-semibold text-ink">{{ __('commerce::settlement.due') }}</dt><dd class="font-semibold text-ink" dir="ltr">{{ $due->format() }}</dd></div>
                </dl>
                <div class="mt-5 border-t border-line pt-4">@include('commerce::partials.settlement', ['figures' => $figures, 'order' => $order])</div>
            </x-ui::card>

            <x-ui::card>
                <h2 class="mb-4 font-semibold text-ink">{{ __('commerce::settlement.gateway') }}</h2>
                @if (! $due->isPositive())
                    <x-ui::empty-state compact icon="check-circle" :title="__('commerce::settlement.nothing_due')" :description="__('commerce::settlement.nothing_due_hint')" />
                @elseif ($gateways->isEmpty())
                    <x-ui::empty-state compact variant="error" :title="__('commerce::settlement.no_gateway')" :description="__('commerce::settlement.no_gateway_hint')" />
                @else
                    <form method="POST" action="{{ vendor_route('finance.settle.pay', ['order' => $order->uuid]) }}" class="grid gap-3">
                        @csrf
                        <fieldset class="grid gap-2">
                            <legend class="sr-only">{{ __('commerce::settlement.gateway') }}</legend>
                            @foreach ($gateways as $gateway)
                                <label class="flex cursor-pointer items-center gap-3 rounded-control border border-line px-3 py-2.5 has-checked:border-primary has-checked:bg-primary-subtle">
                                    <input type="radio" name="gateway" value="{{ $gateway->code->value }}" class="ui-radio" @checked($loop->first) required>
                                    <span class="ui-gateway-tile grid size-8 shrink-0 place-items-center rounded-lg text-xs font-medium" data-gateway="{{ $gateway->code->value }}" aria-hidden="true">{{ $gateway->code->initials() }}</span>
                                    <span class="text-sm font-medium text-ink">{{ $gateway->code->brand() }}</span>
                                </label>
                            @endforeach
                        </fieldset>
                        @error('gateway')<p class="text-sm text-danger">{{ $message }}</p>@enderror
                        <x-ui::stripe-card :gateways="$gateways" :purpose="\App\Modules\Integrations\Enums\GatewayPurpose::VendorPayments" />
                        <x-ui::button type="submit" block icon="credit-card">{{ __('commerce::settlement.pay', ['amount' => $due->format()]) }}</x-ui::button>
                    </form>
                @endif
            </x-ui::card>
        </div>
    </div>
</x-vendor::layout>
