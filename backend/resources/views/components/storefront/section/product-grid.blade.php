{{--
    Product grid section (Ecommet "Best Selling Items" 1:1074, 5 × 3 cards, ribbon) with a
    "See More" that appends the next page. Like the old Ecommet home the heading stays and an
    empty-state line shows when there are no products (pass `empty`); without `empty` it hides.
--}}
@props(['result', 'id', 'title' => null, 'icon' => null, 'ribbon' => false, 'tag' => null, 'moreUrl' => null, 'empty' => null])
@if (! $result->isEmpty() || $empty)
    <section {{ $attributes->class(['grid gap-8']) }} @if ($title) aria-labelledby="{{ $id }}-title" @endif>
        @if ($title)
            <h2 id="{{ $id }}-title" class="eco-section-title justify-self-center" data-reveal="fade-up">@if ($icon)<x-ui::icon :name="$icon" :size="24" />@endif{{ $title }}</h2>
        @endif
        @if ($result->isEmpty())
            <p class="py-10 text-center text-sm text-eco-gray" data-reveal="fade">{{ $empty }}</p>
        @else
            <div id="{{ $id }}" class="eco-card-grid lg:px-7" data-reveal-stagger>
                @include(\App\Modules\Storefront\Support\StorefrontView::resolve('partials.card-grid-items'), ['cards' => $result->cards, 'variant' => 'grid', 'tag' => $tag, 'ribbon' => $ribbon])
            </div>
            <x-sf::section.load-more :url="$result->hasMore() ? $moreUrl : null" :target="'#'.$id" />
        @endif
    </section>
@endif
