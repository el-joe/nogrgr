{{--
    Categories browser (Ecommet m 1:2979): top-level list on the side, round sub-category
    tiles beside it; desktop shows the same split wider. Tabs switch panels without a reload.
--}}
<x-sf::layout :seo="$seo" page="categories" nav="categories">
    <h1 class="sr-only">{{ $title }}</h1>
    @if ($tree === [])
        <section class="eco-container grid justify-items-center gap-4 py-24 text-center">
            <x-ui::icon name="grid" :size="48" class="text-eco-semi" />
            <h2 class="text-xl font-medium">{{ __st('catalog.categories.empty_title') }}</h2>
            <p class="max-w-md text-sm text-eco-gray">{{ __st('catalog.categories.empty_body') }}</p>
            <a href="{{ route('storefront.home') }}" class="eco-pill-dark">{{ __st('catalog.empty.browse_all') }}</a>
        </section>
    @else
        <div class="eco-container flex min-h-[70vh] gap-0 px-0 lg:gap-6 lg:px-6 lg:py-8" data-simple-tabs>
            <ul class="w-[132px] shrink-0 border-e border-eco-stroke py-2 lg:w-[260px]" role="tablist" aria-orientation="vertical" aria-label="{{ __st('shell.nav.categories') }}">
                @foreach ($tree as $index => $node)
                    <li>
                        <button type="button" role="tab" class="eco-mega-cat !px-2 text-sm lg:!px-4 lg:text-base" id="sf-cat-tab-{{ $node['category']->id }}" aria-controls="sf-cat-panel-{{ $node['category']->id }}" aria-selected="{{ $index === 0 ? 'true' : 'false' }}">
                            <span class="line-clamp-2">{{ $node['category']->name }}</span>
                        </button>
                    </li>
                @endforeach
            </ul>
            <div class="min-w-0 flex-1 px-1.5 py-3 lg:px-0">
                @foreach ($tree as $index => $node)
                    <section id="sf-cat-panel-{{ $node['category']->id }}" role="tabpanel" aria-labelledby="sf-cat-tab-{{ $node['category']->id }}" class="grid gap-4" @if ($index !== 0) hidden @endif>
                        <a href="{{ route('storefront.category', ['slug' => $node['category']->slug]) }}" class="inline-flex items-center gap-1 px-1 text-sm font-medium hover:underline">
                            {{ __st('shell.nav.shop_all', ['category' => $node['category']->name]) }}<x-ui::icon name="arrow-right" :size="16" class="rtl:-scale-x-100" />
                        </a>
                        <div class="grid grid-cols-3 gap-x-1 gap-y-4 sm:grid-cols-4 lg:grid-cols-6 lg:gap-6" data-reveal-stagger>
                            @foreach ($node['children'] !== [] ? $node['children'] : [$node['category']] as $child)
                                <a href="{{ route('storefront.category', ['slug' => $child->slug]) }}" class="eco-cat-tile text-xs lg:text-sm [&_img]:size-[57px] [&_.eco-cat-ph]:size-[57px] lg:[&_img]:size-[82px] lg:[&_.eco-cat-ph]:size-[82px]" data-reveal="zoom">
                                    <x-sf::category-image :category="$child" :size="82" />
                                    <span class="line-clamp-2">{{ $child->name }}</span>
                                </a>
                            @endforeach
                        </div>
                    </section>
                @endforeach
            </div>
        </div>
    @endif
</x-sf::layout>
