{{--
    Souqify categories browser (shared inner page, Souqify styling of the Ecommet m 1:2979
    split): top-level list on the side, round sub-category circles beside it. Tabs switch
    panels without a reload ([data-simple-tabs], catalog/ui.js). Inputs: $tree, $title, $seo.
--}}
<x-sf::layout :seo="$seo" page="categories" nav="categories">
    <h1 class="sr-only">{{ $title }}</h1>
    @if ($tree === [])
        <section class="sq-wrap sq-empty py-24">
            <span class="sq-empty-icon"><x-ui::icon name="grid" :size="40" /></span>
            <h2>{{ __st('catalog.categories.empty_title') }}</h2>
            <p class="max-w-md">{{ __st('catalog.categories.empty_body') }}</p>
            <a href="{{ route('storefront.home') }}" class="sq-btn sq-btn-ink mo-press mt-2">{{ __st('catalog.empty.browse_all') }}</a>
        </section>
    @else
        <div class="sq-wrap flex min-h-[70vh] gap-0 px-0 lg:gap-8 lg:px-12 lg:py-8" data-simple-tabs>
            <ul class="w-[124px] shrink-0 bg-sq-soft py-2 lg:w-[260px] lg:rounded-xl lg:bg-white lg:p-3 lg:shadow-[0_4px_7px_rgb(0_0_0/0.08)]" role="tablist" aria-orientation="vertical" aria-label="{{ __st('shell.nav.categories') }}">
                @foreach ($tree as $index => $node)
                    <li>
                        <button type="button" role="tab" class="relative flex min-h-12 w-full items-center px-3 text-start text-sm text-sf-muted transition-colors hover:text-sq-ink aria-selected:bg-white aria-selected:font-medium aria-selected:text-sq-ink lg:rounded-lg lg:px-4 lg:text-base lg:aria-selected:bg-sq-tint lg:aria-selected:text-sq-primary aria-selected:before:absolute aria-selected:before:inset-y-3 aria-selected:before:start-0 aria-selected:before:w-[3px] aria-selected:before:rounded-full aria-selected:before:bg-sq-primary" id="sq-cat-tab-{{ $node['category']->id }}" aria-controls="sq-cat-panel-{{ $node['category']->id }}" aria-selected="{{ $index === 0 ? 'true' : 'false' }}">
                            <span class="line-clamp-2">{{ $node['category']->name }}</span>
                        </button>
                    </li>
                @endforeach
            </ul>
            <div class="min-w-0 flex-1 px-3 py-4 lg:px-0">
                @foreach ($tree as $index => $node)
                    <section id="sq-cat-panel-{{ $node['category']->id }}" role="tabpanel" aria-labelledby="sq-cat-tab-{{ $node['category']->id }}" class="grid content-start gap-5" @if ($index !== 0) hidden @endif>
                        <a href="{{ route('storefront.category', ['slug' => $node['category']->slug]) }}" class="inline-flex w-fit items-center gap-1 text-sm font-medium text-sq-primary hover:underline">
                            {{ __st('shell.nav.shop_all', ['category' => $node['category']->name]) }}<x-ui::icon name="arrow-right" :size="16" class="rtl:-scale-x-100" />
                        </a>
                        <div class="grid grid-cols-3 gap-x-2 gap-y-5 sm:grid-cols-4 lg:grid-cols-6 lg:gap-6" data-reveal-stagger>
                            @foreach ($node['children'] !== [] ? $node['children'] : [$node['category']] as $child)
                                <a href="{{ route('storefront.category', ['slug' => $child->slug]) }}" class="sq-cat w-auto" data-reveal="zoom">
                                    <x-sf::category-image :category="$child" :size="64" />
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
