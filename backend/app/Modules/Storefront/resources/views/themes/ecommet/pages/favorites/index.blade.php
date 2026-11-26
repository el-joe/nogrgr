{{--
    Account › Wishlist (SF-ECO-033, Figma Storefront additions 101:103953 / m 101:104234):
    search (filters in place, `q` server-side too), product cards with add to cart (Store API
    cart, catalog/cart.js) and remove (storefront.favorites.toggle), empty states.
    Inputs: $cards (ProductCardData), $total, $search, $seo.
--}}
<x-sf::account.layout :title="__st('account.wishlist.title')" active="wishlist" page="wishlist" :seo="$seo">
    @if ($total > 0)
        <form method="GET" action="{{ route('storefront.favorites') }}" role="search" class="sf-control min-h-10" data-filter-search="wishlist">
            <x-ui::icon name="search" :size="16" class="shrink-0 text-sf-muted" />
            <label for="sf-wishlist-q" class="sr-only">{{ __st('account.wishlist.search') }}</label>
            <input id="sf-wishlist-q" type="search" name="q" value="{{ $search }}" maxlength="100" placeholder="{{ __st('account.wishlist.search') }}" autocomplete="off" class="!h-9">
        </form>
    @endif

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4" data-filter-list="wishlist" data-reveal-stagger>
        @foreach ($cards as $card)
            <article class="sf-card group flex flex-col overflow-hidden transition-[opacity,transform] duration-300 data-[removing]:scale-95 data-[removing]:opacity-0" data-reveal="fade-up" data-product="{{ $card->id }}" data-filter-item data-filter-text="{{ \Illuminate\Support\Str::lower($card->name) }}" data-wishlist-card>
                <a href="{{ $card->url }}" class="relative block aspect-square overflow-hidden bg-[#f6f6f6]">
                    @if ($card->image)
                        <img src="{{ $card->image }}" alt="{{ $card->name }}" width="300" height="300" loading="lazy" decoding="async" class="size-full object-cover transition-transform duration-500 group-hover:scale-105 motion-reduce:transition-none" data-fly-image>
                    @else
                        <span class="grid size-full place-items-center text-sf-muted"><x-ui::icon name="image" :size="40" /></span>
                    @endif
                    @unless ($card->inStock)
                        <span class="absolute inset-x-0 bottom-0 bg-black/70 py-1 text-center text-xs text-white">{{ __st('account.wishlist.out_of_stock') }}</span>
                    @endunless
                </a>
                <div class="flex flex-1 flex-col gap-1 p-2.5">
                    <a href="{{ $card->url }}" class="truncate text-sm font-medium leading-5 text-sf-ink hover:underline" title="{{ $card->name }}">{{ $card->name }}</a>
                    <p class="flex flex-wrap items-baseline gap-x-1.5 whitespace-nowrap">
                        <span class="text-sm font-medium text-sf-accent">{{ $card->price }}</span>
                        @if ($card->compare)<span class="text-[11px] text-sf-muted line-through"><span class="sr-only">{{ __st('account.wishlist.was') }}</span>{{ $card->compare }}</span>@endif
                    </p>
                    <div class="mt-auto flex items-center gap-2 pt-1">
                        @if ($card->hasVariants || ! $card->inStock)
                            <a href="{{ $card->url }}" class="sf-btn sf-btn-cta min-h-8 flex-1 px-3 text-xs">{{ __st('account.wishlist.choose_options') }}</a>
                        @else
                            <button type="button" class="sf-btn sf-btn-cta min-h-8 flex-1 px-3 text-xs" data-add-to-cart data-product-id="{{ $card->id }}" data-state="idle">{{ __st('account.wishlist.add_to_cart') }}</button>
                        @endif
                        <button type="button" class="grid size-8 shrink-0 place-items-center rounded-full text-sf-danger transition hover:bg-sf-danger-bg" data-wishlist-remove="{{ $card->id }}" aria-label="{{ __st('account.wishlist.remove', ['name' => $card->name]) }}"><x-ui::icon name="trash" :size="18" /></button>
                    </div>
                </div>
            </article>
        @endforeach
    </div>

    @if ($total > 0)
        <x-sf::order.empty icon="search" :title="__st('account.wishlist.no_results', ['term' => $search ?? ''])" data-filter-empty data-filter-term-title="{{ __st('account.wishlist.no_results', ['term' => ':term']) }}" :hidden="$cards !== []" />
    @endif
    <x-sf::order.empty icon="star" :title="__st('account.wishlist.empty_title')" :text="__st('account.wishlist.empty_text')" :action="__st('account.wishlist.browse')" :href="Route::has('storefront.home') ? route('storefront.home') : null" data-wishlist-empty :hidden="$total > 0" data-reveal="fade-up" />
</x-sf::account.layout>
