{{--
    Product grid section (Ecommet "Best Selling Items" 1:1074, 5 × 3 cards, ribbon) with a
    "See More" that appends the next page. Hidden when the result is empty.
--}}
@props(['result', 'id', 'title' => null, 'icon' => null, 'ribbon' => false, 'tag' => null, 'moreUrl' => null])
@if (! $result->isEmpty())
    <section {{ $attributes->class(['grid gap-8']) }} @if ($title) aria-labelledby="{{ $id }}-title" @endif>
        @if ($title)
            <h2 id="{{ $id }}-title" class="eco-section-title justify-self-center" data-reveal="fade-up">@if ($icon)<x-ui::icon :name="$icon" :size="24" />@endif{{ $title }}</h2>
        @endif
        <div id="{{ $id }}" class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5 lg:gap-4 lg:px-7" data-reveal-stagger>
            @include(\App\Modules\Storefront\Support\StorefrontView::resolve('partials.card-grid-items'), ['cards' => $result->cards, 'variant' => 'grid', 'tag' => $tag, 'ribbon' => $ribbon])
        </div>
        <x-sf::section.load-more :url="$result->hasMore() ? $moreUrl : null" :target="'#'.$id" />
    </section>
@endif
