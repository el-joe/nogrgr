{{--
    Elora order history (client Figma 1:18739, empty 1:19347, phone m 1:23374 / empty 1:23542).
    Same content and actions as the Ecommet list: search + status filters (GET), view, track,
    cancel (2-step modal), reorder, review (modal), pager. Phones follow the Figma card
    (whole card opens the order, "View" at the bottom end) with the other actions as a small
    row under it. Inputs: $rows (AccountOrderPages rows), $page, $tab, $tabs, $search, $seo.
--}}
@php
    use App\Modules\Storefront\Services\Account\AccountOrderPages;
    $money = fn ($amount) => AccountOrderPages::money($amount);
    $filtered = $tab !== 'all' || filled($search);
    $shop = Route::has('storefront.home') ? route('storefront.home') : null;
    $statusUrl = fn (string $key) => route('storefront.orders.index', array_filter(['status' => $key === 'all' ? null : $key, 'q' => $search]));
@endphp
<x-sf::elora.account.layout :title="__st('orders.title')" :crumb="__st('account.nav.orders')" active="orders" page="orders" :seo="$seo" :heading="false" :order-tab="$tab">
    <x-slot:mobileHead>
        <div class="eac-mhead-row">
            <p class="eac-mhead-title eac-mhead-title-lg" aria-hidden="true">{{ __st('orders.title') }}</p>
        </div>
    </x-slot:mobileHead>
    <x-slot:strip>
        <div class="eac-orders-tools">
            <form method="GET" action="{{ route('storefront.orders.index') }}" role="search" class="eac-search" data-orders-search>
                @if ($tab !== 'all')<input type="hidden" name="status" value="{{ $tab }}">@endif
                <label for="eac-orders-q" class="sr-only">{{ __st('orders.search_label') }}</label>
                <input id="eac-orders-q" type="search" name="q" value="{{ $search }}" maxlength="100" placeholder="{{ __st('orders.search_placeholder') }}" autocomplete="off" enterkeyhint="search">
                <button type="submit" aria-label="{{ __st('orders.search_label') }}"><x-ui::icon name="elo-search-lg" :size="24" /></button>
            </form>
            <nav class="eac-strip" aria-label="{{ __st('orders.filters_label') }}" data-elo-drag>
                @foreach ($tabs as $key)
                    <a href="{{ $statusUrl($key) }}" @if ($tab === $key) aria-current="page" @endif>{{ __st('orders.tabs.'.$key) }}</a>
                @endforeach
            </nav>
        </div>
    </x-slot:strip>

    {{-- Desktop search (below lg it sits in the header strip above). --}}
    <form method="GET" action="{{ route('storefront.orders.index') }}" role="search" class="eac-search eac-search-desktop">
        @if ($tab !== 'all')<input type="hidden" name="status" value="{{ $tab }}">@endif
        <label for="eac-orders-q-lg" class="sr-only">{{ __st('orders.search_label') }}</label>
        <input id="eac-orders-q-lg" type="search" name="q" value="{{ $search }}" maxlength="100" placeholder="{{ __st('orders.search_placeholder') }}" autocomplete="off" enterkeyhint="search">
        <button type="submit" aria-label="{{ __st('orders.search_label') }}"><x-ui::icon name="elo-search-lg" :size="24" /></button>
    </form>

    <div class="eac-orders">
        <p class="eac-info" data-orders-info>
            <x-ui::icon name="elo-info" :size="24" />
            <span>{{ __st('orders.info_banner') }}</span>
        </p>

        @forelse ($rows as $order)
            @php
                $first = $order['items'][0] ?? null;
                $more = max(count($order['items']) - 1, 0);
                $detail = route('storefront.orders.show', $order['uuid']);
                $reviewItems = collect($order['items'])->where('reviewable', true)->map(fn ($item) => ['id' => $item['id'], 'name' => $item['name'], 'image' => $item['image_url'], 'variant' => $item['variant']])->values();
            @endphp
            <article class="eac-order" data-reveal="fade-up" data-order-card="{{ $order['uuid'] }}">
                <header class="eac-order-head">
                    <div class="min-w-0">
                        <h2 class="eac-order-number" dir="ltr">{{ $order['number'] }}</h2>
                        <p class="eac-order-date">{{ __st('orders.item_count', ['count' => $order['items_count']]) }} · {{ $order['placed']?->translatedFormat('M j, Y') }}</p>
                    </div>
                    <x-sf::elora.account.status :tone="$order['tone']" data-order-status>{{ $order['status_label'] }}</x-sf::elora.account.status>
                </header>
                <div class="eac-order-body">
                    <a href="{{ $detail }}" class="eac-order-item">
                        <x-sf::elora.account.thumbs :images="collect($order['items'])->pluck('image_url')->all()" />
                        <span class="eac-order-text">
                            <span class="eac-order-name" dir="auto">{{ $first['name'] ?? '' }}</span>
                            @if ($more)<span class="eac-order-more">{{ __st('elora_pages.account.more_items', ['count' => $more]) }}</span>@endif
                            <span class="eac-order-price">{{ $money($order['totals']['grand_total']) }}</span>
                        </span>
                    </a>
                    <a href="{{ $detail }}" class="eac-order-view" aria-label="{{ __st('orders.view_details') }} {{ $order['number'] }}"><x-ui::icon name="elo-eye" :size="20" />{{ __st('elora_pages.account.view') }}</a>
                </div>
                <div class="eac-order-actions">
                    <a href="{{ $detail }}" class="eac-btn eac-btn-ink eac-act-view mo-press">{{ __st('orders.view_details') }}<x-ui::icon name="chevron-right" :size="16" class="rtl:-scale-x-100" /></a>
                    @if ($order['trackable'])
                        <a href="{{ route('storefront.orders.tracking', $order['uuid']) }}" class="eac-btn mo-press"><x-ui::icon name="truck" :size="16" />{{ __st('elora_pages.account.track') }}</a>
                    @endif
                    @if ($order['cancellable'])
                        <button type="button" class="eac-btn eac-btn-danger mo-press" data-sheet-open="sf-cancel-order" data-cancel-order="{{ json_encode(['url' => route('store-api.v1.orders.cancel', $order['uuid']), 'number' => $order['number'], 'paid' => $order['payment_status'] === 'paid' ? $money($order['totals']['grand_total']) : null], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}">{{ __st('orders.cancel') }}</button>
                    @endif
                    <button type="button" class="eac-btn mo-press" data-reorder="{{ json_encode(collect($order['items'])->map(fn ($item) => ['store_product_id' => $item['store_product_id'], 'variant_id' => $item['variant_id'], 'quantity' => $item['quantity'], 'name' => $item['name']])->values(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}">{{ __st('orders.reorder') }}</button>
                    @if ($reviewItems->isNotEmpty())
                        <button type="button" class="eac-btn mo-press" data-sheet-open="sf-write-review" data-review="{{ json_encode(['url' => route('store-api.v1.orders.items.review', ['order' => $order['uuid'], 'item' => '__ITEM__']), 'items' => $reviewItems], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}">{{ __st('orders.write_review') }}</button>
                    @endif
                </div>
            </article>
        @empty
            @if ($filtered && filled($search))
                <x-sf::elora.account.empty icon="search" :title="__st('orders.no_results_title')" :text="__st('orders.no_results_text')" :action="__st('orders.clear_search')" :href="route('storefront.orders.index', array_filter(['status' => $tab === 'all' ? null : $tab]))" data-reveal="fade-up" />
            @else
                <x-sf::elora.account.empty :title="__st('orders.empty_title')" :text="__st('orders.empty_text')" :action="__st('orders.start_shopping')" :href="$shop" data-reveal="fade-up" />
            @endif
        @endforelse

        <x-sf::order.pagination :page="$page" />
    </div>

    <x-sf::order.cancel-modal />
    <x-sf::order.review-modal />
</x-sf::elora.account.layout>
