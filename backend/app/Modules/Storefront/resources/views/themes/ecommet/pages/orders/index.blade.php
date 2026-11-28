{{--
    Account › Orders (SF-ECO-027…027e, Figma Storefront additions 100:101877 / m 100:102522;
    client frames 1:1871 1:2353 1:2384 1:4110 1:2518 1:2615). Status chips + search are GET
    filters; cards: view, track, cancel (2-step modal), reorder, review (modal).
    Inputs: $rows (AccountOrderPages rows), $page, $tab, $tabs, $search, $seo.
--}}
@php
    use App\Modules\Storefront\Services\Account\AccountOrderPages;
    $money = fn ($amount) => AccountOrderPages::money($amount);
    $filtered = $tab !== 'all' || filled($search);
    $shop = Route::has('storefront.home') ? route('storefront.home') : null;
@endphp
<x-sf::account.layout :title="__st('orders.title')" active="orders" page="orders" :seo="$seo">
    <form method="GET" action="{{ route('storefront.orders.index') }}" role="search" class="sf-control" data-orders-search>
        @if ($tab !== 'all')<input type="hidden" name="status" value="{{ $tab }}">@endif
        <x-ui::icon name="search" :size="18" class="shrink-0 text-sf-muted" />
        <label for="sf-orders-q" class="sr-only">{{ __st('orders.search_label') }}</label>
        <input id="sf-orders-q" type="search" name="q" value="{{ $search }}" maxlength="100" placeholder="{{ __st('orders.search_placeholder') }}" autocomplete="off" enterkeyhint="search">
    </form>

    <nav aria-label="{{ __st('orders.filters_label') }}" class="flex flex-wrap gap-2" data-reveal-stagger>
        @foreach ($tabs as $key)
            <a href="{{ route('storefront.orders.index', array_filter(['status' => $key === 'all' ? null : $key, 'q' => $search])) }}" class="sf-pill-tab font-medium" @if ($tab === $key) aria-current="page" @endif>{{ __st('orders.tabs.'.$key) }}</a>
        @endforeach
    </nav>

    <p class="flex items-center gap-2.5 rounded-lg bg-sf-warning-bg px-4 py-3 text-sm text-sf-ink" data-orders-info>
        <x-ui::icon name="info" :size="18" class="shrink-0 text-sf-warning" />
        <span>{{ __st('orders.info_banner') }}</span>
    </p>

    @forelse ($rows as $order)
        @php
            $first = $order['items'][0] ?? null;
            $more = max(count($order['items']) - 1, 0);
            $reviewItems = collect($order['items'])->where('reviewable', true)->map(fn ($item) => ['id' => $item['id'], 'name' => $item['name'], 'image' => $item['image_url'], 'variant' => $item['variant']])->values();
        @endphp
        <article class="sf-card overflow-hidden" data-reveal="fade-up" data-order-card="{{ $order['uuid'] }}">
            <header class="flex items-center gap-3 border-b border-sf-border px-4 py-3">
                <div class="grid min-w-0 flex-1 gap-0.5">
                    <h2 class="truncate text-sm font-medium leading-5 text-sf-ink" dir="ltr">{{ $order['number'] }}</h2>
                    <p class="text-xs leading-4 text-sf-muted">{{ __st('orders.item_count', ['count' => $order['items_count']]) }} · {{ $order['placed']?->translatedFormat('M j, Y') }}</p>
                </div>
                <x-sf::order.badge :tone="$order['tone']" data-order-status>{{ $order['status_label'] }}</x-sf::order.badge>
            </header>
            <a href="{{ route('storefront.orders.show', $order['uuid']) }}" class="flex items-center gap-3 px-4 py-3">
                <x-sf::order.thumb :src="$first['image_url'] ?? null" />
                <span class="grid min-w-0 flex-1 gap-0.5 text-sm font-medium leading-5">
                    <span class="truncate text-sf-ink">{{ $first['name'] ?? '' }}@if ($more) · {{ __st('orders.more_items', ['count' => $more]) }}@endif</span>
                    <span class="text-sf-accent">{{ $money($order['totals']['grand_total']) }}</span>
                </span>
            </a>
            <div class="flex flex-col gap-2 px-4 pb-3.5 sm:flex-row">
                <a href="{{ route('storefront.orders.show', $order['uuid']) }}" class="sf-btn sf-btn-cta min-h-9 flex-1 px-4 text-xs">{{ __st('orders.view_details') }}<x-ui::icon name="chevron-right" :size="16" class="rtl:-scale-x-100" /></a>
                @if ($order['trackable'])
                    <a href="{{ route('storefront.orders.tracking', $order['uuid']) }}" class="sf-btn sf-btn-outline min-h-9 flex-1 px-4 text-xs"><x-ui::icon name="truck" :size="18" />{{ __st('orders.track') }}</a>
                @endif
                @if ($order['cancellable'])
                    <button type="button" class="sf-btn sf-btn-danger-outline min-h-9 flex-1 px-4 text-xs" data-sheet-open="sf-cancel-order" data-cancel-order="{{ json_encode(['url' => route('store-api.v1.orders.cancel', $order['uuid']), 'number' => $order['number'], 'paid' => $order['payment_status'] === 'paid' ? $money($order['totals']['grand_total']) : null], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}">{{ __st('orders.cancel') }}</button>
                @endif
                <button type="button" class="sf-btn sf-btn-outline min-h-9 flex-1 px-4 text-xs" data-reorder="{{ json_encode(collect($order['items'])->map(fn ($item) => ['store_product_id' => $item['store_product_id'], 'variant_id' => $item['variant_id'], 'quantity' => $item['quantity'], 'name' => $item['name']])->values(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}">{{ __st('orders.reorder') }}</button>
                @if ($reviewItems->isNotEmpty())
                    <button type="button" class="sf-btn sf-btn-outline min-h-9 flex-1 px-4 text-xs" data-sheet-open="sf-write-review" data-review="{{ json_encode(['url' => route('store-api.v1.orders.items.review', ['order' => $order['uuid'], 'item' => '__ITEM__']), 'items' => $reviewItems], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}"><x-ui::icon name="star" :size="18" />{{ __st('orders.write_review') }}</button>
                @endif
            </div>
        </article>
    @empty
        @if ($filtered && filled($search))
            <x-sf::order.empty icon="search" :title="__st('orders.no_results_title')" :text="__st('orders.no_results_text')" :action="__st('orders.clear_search')" :href="route('storefront.orders.index', array_filter(['status' => $tab === 'all' ? null : $tab]))" data-reveal="fade-up" />
        @else
            <x-sf::order.empty :title="__st('orders.empty_title')" :text="__st('orders.empty_text')" :action="__st('orders.start_shopping')" :href="$shop" data-reveal="fade-up" />
        @endif
    @endforelse

    <x-sf::order.pagination :page="$page" />

    <x-sf::order.cancel-modal />
    <x-sf::order.review-modal />
</x-sf::account.layout>
