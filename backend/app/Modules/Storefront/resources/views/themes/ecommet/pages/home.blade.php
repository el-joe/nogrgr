{{--
    Ecommet home (Figma 1:1002 desktop, 1:2656 phone; phone alt "Star" 1:2710 = the
    5-Star Rated tab of the phone product tabs). Every section hides itself when empty;
    order / on-off from the store's page builder (VEN-032) or the theme's home sections (ADM-087).
--}}
<x-sf::layout :seo="$seo" nav="home" page="home">

    @php
        $store = \App\Modules\Storefront\Support\StorefrontTheme::storeName();
        /* Section order / visibility from the theme's home sections (ADM-087, decision #120);
           consecutive "why choose" + "deals" share one container as in the design. */
        $homeSections ??= \App\Modules\Storefront\Support\ThemeSectionLibrary::sections(\App\Modules\Storefront\Enums\ThemeFamily::Ecommet, 1);
        $groups = [];
        foreach ($homeSections as $section) {
            $key = $section['type'];
            $paired = in_array($key, ['why-choose', 'deals'], true);
            $last = array_key_last($groups);
            if ($paired && $last !== null && $groups[$last][0] === 'pair') {
                $groups[$last][1][] = $section;
            } else {
                $groups[] = [$paired ? 'pair' : $key, [$section]];
            }
        }
    @endphp
    <h1 class="sr-only">{{ $store }}</h1>
    @foreach ($groups as [$group, $members])
        @php
            $section = $members[0];
        @endphp
        @switch($group)
            @case('category-strip')
                <x-sf::section.category-strip :tabs="$tabs" class="lg:hidden" />
                @break
            @case('hero')
                <x-sf::section.hero :banners="$section['banners'] ?? $hero" />
                @break
            @case('pair')
                <div class="eco-container mt-4 grid grid-cols-1 gap-6 max-lg:px-0 lg:mt-6">
                    @foreach ($members as $member)
                        @if ($member['type'] === 'why-choose')
                            <x-sf::section.why-choose :store="$store" class="hidden lg:block" />
                        @else
                            <x-sf::section.deals :deals="$member['cards'] ?? $deals" :clearance="$clearance" class="max-lg:ps-4" />
                        @endif
                    @endforeach
                </div>
                @break
            @case('interests')
                <x-sf::section.interests :tabs="$tabs" :result="$interests" :kicker="$flash?->title" class="eco-wide mt-16 hidden lg:grid" />
                @break
            @case('flash-band')
                <x-sf::section.flash-band :sale="$flash" class="mt-6 lg:mx-auto lg:mt-10 lg:max-w-[1320px]" />
                @break
            @case('best-grid')
                <x-sf::section.product-grid :result="$best" id="sf-best-grid" :title="$section['title_text'] ?? __st('catalog.home.best_selling')" icon="award" ribbon :more-url="route('storefront.best-sellers', ['page' => 2, 'per' => 15, 'ribbon' => 1])" class="eco-wide mb-16 mt-10 hidden lg:grid" />
                @break
            @case('mobile-tabs')
                <x-sf::section.mobile-tabs class="mb-6 mt-6 lg:hidden" :tabs="[
                    ['key' => 'best', 'label' => __st('shell.nav.best_sellers'), 'icon' => 'award', 'result' => $best, 'more' => route('storefront.best-sellers', ['page' => 2, 'per' => 15, 'ribbon' => 1]), 'ribbon' => true],
                    ['key' => 'rated', 'label' => __st('shell.nav.top_rated'), 'icon' => 'star', 'result' => $rated, 'more' => route('storefront.top-rated', ['page' => 2, 'per' => 15])],
                    ['key' => 'new', 'label' => __st('shell.nav.new_in'), 'icon' => 'sparkles', 'result' => $newest, 'more' => route('storefront.new-in', ['page' => 2, 'per' => 15]), 'tag' => 'new'],
                ]" />
                @break
        @endswitch
    @endforeach

    @if ($deals === [] && $clearance === [] && $interests->isEmpty() && $best->isEmpty() && $hero === [])
        <section class="eco-container grid justify-items-center gap-4 py-24 text-center">
            <x-ui::icon name="store" :size="48" class="text-eco-semi" />
            <h2 class="text-xl font-medium">{{ __st('catalog.home.empty_title') }}</h2>
            <p class="max-w-md text-sm text-eco-gray">{{ __st('catalog.home.empty_body') }}</p>
        </section>
    @endif
</x-sf::layout>
