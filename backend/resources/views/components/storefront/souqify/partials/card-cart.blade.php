{{-- Souqify card cart button: add to cart (spinner → check, fly-to-cart) or a link to the product when it needs options / is sold out. --}}
@if ($card->hasVariants || ! $card->inStock)
    <a href="{{ $card->url }}" class="{{ $class }} mo-press" aria-label="{{ $card->inStock ? __st('catalog.card.choose_options', ['name' => $card->name]) : __st('catalog.card.view', ['name' => $card->name]) }}"><x-ui::icon name="cart-add" :size="$size" /></a>
@else
    <button type="button" class="{{ $class }} mo-press" data-add-to-cart data-product-id="{{ $card->id }}" data-state="idle" aria-label="{{ __st('catalog.card.add_to_cart', ['name' => $card->name]) }}">
        <span class="mo-state mo-state-idle"><x-ui::icon name="cart-add" :size="$size" /></span>
        <span class="mo-state mo-state-loading"><x-ui::icon name="refresh" :size="20" class="mo-spin" /></span>
        <span class="mo-state mo-state-done"><x-ui::icon name="check" :size="20" /></span>
    </button>
@endif
