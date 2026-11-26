{{--
    Printable invoice of the customer's order (orders/{uuid}/invoice, linked as "Download
    invoice"): browser print / Save as PDF. Built from the same order data as the details page.
--}}
@php
    use App\Modules\Storefront\Services\Account\AccountOrderPages;
    use App\Modules\Storefront\Support\StorefrontTheme;
    $money = fn ($amount) => AccountOrderPages::money($amount);
    $d = $detail;
    $t = $d['totals'];
    $store = StorefrontTheme::storeName();
@endphp
<x-layouts.storefront :family="StorefrontTheme::family()" :title="__st('orders.invoice_title', ['number' => $d['number']])" :full-title="__st('orders.invoice_title', ['number' => $d['number']]).' | '.$store">
    <x-slot:head><x-sf::seo :seo="$seo" /></x-slot:head>
    <main class="sf-page min-h-screen bg-[#f5f5f5] px-4 py-6 print:bg-white print:p-0" data-sf-account>
        <div class="mx-auto mb-4 flex max-w-[840px] flex-wrap items-center justify-between gap-3 print:hidden">
            <a href="{{ route('storefront.orders.show', $d['uuid']) }}" class="sf-btn sf-btn-outline min-h-9 px-4 text-xs"><x-ui::icon name="chevron-left" :size="16" class="rtl:-scale-x-100" />{{ __st('orders.back_to_order') }}</a>
            <button type="button" class="sf-btn sf-btn-cta min-h-9 px-4 text-xs" data-print-invoice><x-ui::icon name="printer" :size="16" />{{ __st('orders.print') }}</button>
        </div>
        <article class="sf-card mx-auto grid max-w-[840px] gap-6 p-6 sm:p-8 print:max-w-none print:border-0 print:p-0">
            <header class="flex flex-wrap items-start justify-between gap-4">
                <div class="grid gap-1">
                    <p class="text-lg font-semibold text-sf-ink">{{ $store }}</p>
                    <p class="text-sm text-sf-muted">{{ __st('orders.invoice') }}</p>
                </div>
                <dl class="grid gap-1 text-sm sm:text-end">
                    <div><dt class="inline text-sf-muted">{{ __st('orders.invoice') }}:</dt> <dd class="inline font-medium text-sf-ink" dir="ltr">{{ $d['number'] }}</dd></div>
                    <div><dt class="inline text-sf-muted">{{ __st('orders.date') }}:</dt> <dd class="inline text-sf-ink">{{ $d['placed']?->translatedFormat('M j, Y') }}</dd></div>
                    <div><dt class="inline text-sf-muted">{{ __st('orders.status') }}:</dt> <dd class="inline text-sf-ink">{{ $d['status_label'] }} · {{ $d['payment_status_label'] }}</dd></div>
                </dl>
            </header>
            <section class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-1">
                    <h2 class="text-xs font-semibold uppercase text-sf-muted">{{ __st('orders.bill_to') }}</h2>
                    <x-sf::order.address :address="$d['billing_address'] ?? $d['shipping_address']" class="!text-sm !leading-5 !text-sf-ink" />
                </div>
                <div class="grid gap-1">
                    <h2 class="text-xs font-semibold uppercase text-sf-muted">{{ __st('orders.ship_to') }}</h2>
                    <x-sf::order.address :address="$d['shipping_address']" class="!text-sm !leading-5 !text-sf-ink" />
                </div>
            </section>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[480px] text-sm">
                    <thead class="border-b border-sf-border text-xs text-sf-muted">
                        <tr>
                            <th scope="col" class="py-2 text-start font-medium">{{ __st('orders.product') }}</th>
                            <th scope="col" class="py-2 text-end font-medium">{{ __st('orders.price') }}</th>
                            <th scope="col" class="py-2 text-end font-medium">{{ __st('orders.quantity') }}</th>
                            <th scope="col" class="py-2 text-end font-medium">{{ __st('orders.line_total') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-sf-border">
                        @foreach ($d['items'] as $item)
                            <tr>
                                <td class="py-3 text-sf-ink">{{ $item['name'] }}@if ($item['variant'])<span class="block text-xs text-sf-muted">{{ $item['variant'] }}</span>@endif</td>
                                <td class="py-3 text-end text-sf-ink">{{ $money($item['unit_price']) }}</td>
                                <td class="py-3 text-end text-sf-ink">{{ $item['quantity'] }}</td>
                                <td class="py-3 text-end font-medium text-sf-ink">{{ $money($item['total']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <dl class="ms-auto grid w-full max-w-xs gap-2 text-sm">
                <div class="flex gap-3"><dt class="flex-1 text-sf-muted">{{ __st('orders.items_subtotal') }}</dt><dd class="text-sf-ink">{{ $money($t['subtotal']) }}</dd></div>
                <div class="flex gap-3"><dt class="flex-1 text-sf-muted">{{ __st('orders.shipping') }}</dt><dd class="text-sf-ink">{{ $money($t['shipping']) }}</dd></div>
                @if ((int) $t['flash_discount']['amount'] > 0)<div class="flex gap-3"><dt class="flex-1 text-sf-muted">{{ __st('orders.discount') }}</dt><dd class="text-sf-ink">− {{ $money($t['flash_discount']) }}</dd></div>@endif
                @if ((int) $t['coupon_discount']['amount'] > 0)<div class="flex gap-3"><dt class="flex-1 text-sf-muted">{{ __st('orders.coupon', ['code' => $d['coupon_code']]) }}</dt><dd class="text-sf-ink">− {{ $money($t['coupon_discount']) }}</dd></div>@endif
                @if ((int) $t['tax']['amount'] > 0)<div class="flex gap-3"><dt class="flex-1 text-sf-muted">{{ __st('orders.tax') }}</dt><dd class="text-sf-ink">{{ $money($t['tax']) }}</dd></div>@endif
                <div class="flex gap-3 border-t border-sf-border pt-2 text-base font-semibold text-sf-ink"><dt class="flex-1">{{ __st('orders.total') }}</dt><dd>{{ $money($t['grand_total']) }}</dd></div>
            </dl>
            <p class="text-center text-xs text-sf-muted">{{ __st('orders.invoice_thanks') }}</p>
        </article>
    </main>
</x-layouts.storefront>
