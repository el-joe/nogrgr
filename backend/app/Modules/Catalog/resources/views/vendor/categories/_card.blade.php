{{--
    One product card on the category pages: imported → products._card, own → own._card.
    Data: $card (StoreTaxonomyPageService::cards() row), $removeUrl (optional, inner page).
--}}
<div class="relative grid" data-category-card>
    @if ($card['own'])
        @include('catalog::vendor.own._card', ['product' => $card['product'], 'price' => $card['price']])
    @else
        @include('catalog::vendor.products._card', ['product' => $card['product'], 'view' => $card['view'], 'cheapest' => $card['cheapest']])
    @endif
    @isset($removeUrl)
        <div class="absolute end-3 top-3">
            <x-ui::icon-button icon="x" size="sm" variant="secondary" :label="__('catalog::taxonomy.products.remove')" :data-confirm="__('catalog::taxonomy.products.remove_text')" :data-confirm-title="__('catalog::taxonomy.products.remove_title', ['name' => $card['own'] ? $card['product']->name : $card['view']->name])" data-confirm-method="DELETE" :data-confirm-url="$removeUrl" />
        </div>
    @endisset
</div>
