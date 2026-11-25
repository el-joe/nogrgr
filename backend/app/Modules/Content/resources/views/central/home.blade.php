{{--
    Central home (Figma Home 12:41421, mobile 12:42786). Data: $seo, $themes (≤6),
    $themeCategories, $plans, $posts (≤3), $stats, $testimonials.
--}}
@php
    $appName = config('app.name');
    $brand = '<span class="text-primary">'.e($appName).'</span>';
    $img = fn (string $path): string => \Illuminate\Support\Facades\Vite::asset('resources/images/central/'.$path);
    $has = fn (string $name): bool => \Illuminate\Support\Facades\Route::has($name);
    $stats = collect($stats ?? __('content::site.stats_fallback'));
    $testimonials = collect($testimonials ?? __('content::site.testimonials'));
    $themes = collect($themes ?? []);
    $themeCategories = collect($themeCategories ?? []);
    $plans = collect($plans ?? []);
    $posts = collect($posts ?? []);
    $featuredIndex = $plans->count() >= 3 ? intdiv($plans->count() - 1, 2) : null;
    $term = 'monthly';
    $knownCategoryIcons = ['fashion', 'beauty', 'fitness', 'furniture', 'grocery', 'electronics', 'pets', 'kids'];
@endphp
<x-central::layout :seo="$seo" page="site-home" newsletter>
    {{-- Hero + achievements (12:41422) --}}
    <section class="relative" aria-labelledby="hero-title">
        <span class="site-glow site-glow--hero" aria-hidden="true"></span>
        <div class="relative mx-auto max-w-[1440px] px-4 pt-6 lg:px-[88px] xl:min-h-[650px] xl:pt-[136px]">
            <div class="flex max-w-[688px] flex-col items-center gap-6 text-center max-xl:mx-auto xl:items-start xl:text-start">
                <div class="flex flex-col gap-4 xl:gap-2">
                    <h1 id="hero-title" class="site-hero-title text-xl leading-normal font-medium text-site-ink sm:text-3xl xl:text-[44px] xl:leading-[60px] xl:font-bold">{{ __('content::site.home.hero_title') }}</h1>
                    <p class="text-base tracking-[0.5px] text-site-gray xl:max-w-[606px] xl:text-xl xl:tracking-normal" data-reveal="fade-up" data-reveal-delay="120">{{ __('content::site.home.hero_text') }}</p>
                </div>
                @if ($has('central.templates'))
                    <a href="{{ route('central.templates') }}" class="site-btn order-last inline-flex h-10 items-center justify-center rounded-2xl bg-primary px-8 text-lg font-medium text-white hover:bg-primary-hover max-xl:mt-2 max-xl:w-44 max-xl:rounded-[16px] xl:order-none xl:text-base" data-reveal="fade-up" data-reveal-delay="220">{{ __('content::site.home.hero_cta') }}</a>
                @endif
                {{-- Device composition (Right Side 12:41429) --}}
                <figure class="site-hero-figure relative aspect-[730/600] w-full max-w-[560px] max-xl:order-2 xl:absolute xl:start-[622px] xl:top-[83px] xl:w-[730px] xl:max-w-none" data-parallax>
                    <img src="{{ $img('home/hero-screen.webp') }}" alt="" width="740" height="608" fetchpriority="high"
                        class="site-hero-screen absolute start-[26.44%] top-0 h-[73.17%] w-[73.56%] rounded-lg border border-[rgba(22,70,137,0.78)] object-cover object-top">
                    <img src="{{ $img('home/hero-laptop.webp') }}" alt="{{ __('content::site.home.hero_image_alt', ['app' => $appName]) }}" width="1500" height="1125"
                        class="site-hero-laptop absolute start-[-10.3%] top-[10.35%] w-[95.7%] max-w-none -rotate-[12.66deg]">
                </figure>
            </div>
        </div>

        <div class="relative mx-auto flex max-w-[1124px] flex-col items-center gap-6 px-4 lg:px-0 pt-10 pb-8 lg:gap-12 lg:pt-[80px] lg:pb-[50px]">
            <hr class="site-divider w-full xl:hidden">
            <x-central::section-title :title="__('content::site.home.stats_title')" :text="__('content::site.home.stats_text')" class="max-w-[496px] [&_p]:text-[#929292]" />
            <div class="swiper site-rail w-full" data-rail data-rail-until="1024" data-reveal="fade-up">
                <ul class="swiper-wrapper lg:justify-center lg:gap-5">
                    @foreach ($stats as $stat)
                        <li class="swiper-slide !w-[133px] sm:!w-[200px] lg:!w-[266px]">
                            <div class="flex h-[93px] flex-col items-center justify-center gap-1 rounded-lg bg-site-tint px-2 text-center sm:h-[150px] lg:h-[194px] lg:gap-4 lg:rounded-3xl">
                                <p class="text-base font-bold text-primary lg:text-[32px]" dir="ltr"><span data-count-to="{{ (int) $stat['value'] }}">{{ (int) $stat['value'] }}</span> {{ $stat['suffix'] }}</p>
                                <div class="flex flex-col gap-0.5 text-site-gray lg:gap-2">
                                    <p class="text-xs font-medium text-site-ink lg:text-base lg:text-site-gray lg:uppercase">{{ $stat['label'] }}</p>
                                    <p class="text-[10px] lg:text-base">{{ $stat['caption'] }}</p>
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </section>

    {{-- Features (12:41462 / Component 15) --}}
    <section id="features" class="site-features relative overflow-hidden bg-white px-4 pt-8 pb-10 lg:h-[829px] lg:px-0 lg:pt-[72px] lg:pb-0" aria-labelledby="features-title">
        <hr class="site-divider mx-auto mb-8 max-w-[343px] lg:hidden">
        <x-central::section-title id="features-title" :title="__('content::site.home.features_title')">
            <p class="text-base font-medium text-site-gray lg:mt-4 lg:text-2xl">{!! __('content::site.home.features_text', ['brand' => $brand]) !!}</p>
        </x-central::section-title>
        <span class="site-glow site-glow--features max-lg:hidden" aria-hidden="true"></span>
        <img src="{{ $img('home/features-person.webp') }}" alt="{{ __('content::site.home.features_image_alt') }}" width="828" height="552" loading="lazy"
            class="absolute bottom-0 left-1/2 hidden h-[501px] w-[574px] -translate-x-1/2 object-cover lg:block" data-reveal="fade-up">
        <span class="absolute inset-x-0 bottom-0 hidden h-[182px] bg-gradient-to-b from-white/0 to-white lg:block" aria-hidden="true"></span>
        @php
            $featureCards = [
                ['growth', 'feat-growth.svg', 48, 'lg:start-[88px] lg:top-[249px]', 'slide-start'],
                ['products', 'feat-products.svg', 40, 'lg:start-[128px] lg:top-[521px]', 'slide-start'],
                ['dashboard', 'feat-dashboard.svg', 40, 'lg:end-[88px] lg:top-[251px]', 'slide-end'],
                ['payments', 'feat-payments.svg', 50, 'lg:end-[56px] lg:top-[538px]', 'slide-end'],
            ];
        @endphp
        <ul class="mx-auto mt-6 grid max-w-[343px] grid-cols-2 gap-4 sm:max-w-[600px] lg:contents">
            @foreach ($featureCards as [$key, $icon, $size, $position, $reveal])
                <li class="site-feature flex flex-col items-center justify-center gap-2 rounded-3xl bg-site-tint px-2 py-5 text-center lg:absolute lg:h-[200px] lg:w-[389px] lg:gap-4 lg:rounded-[40px] lg:bg-white/50 lg:px-6 lg:shadow-[0_8px_4px_rgba(213,213,213,0.18)] lg:backdrop-blur-sm {{ $position }}"
                    data-reveal="{{ $reveal }}" data-reveal-delay="{{ $loop->index * 90 }}">
                    <img src="{{ $img('home/'.$icon) }}" alt="" width="{{ $size }}" height="40" class="h-8 w-auto lg:h-10">
                    <h3 class="text-sm font-medium text-primary lg:text-xl">{{ __('content::site.home.features.'.$key.'.title') }}</h3>
                    <p class="text-xs text-site-gray lg:max-w-[340px] lg:text-base">{{ __('content::site.home.features.'.$key.'.text') }}</p>
                </li>
            @endforeach
        </ul>
    </section>

    {{-- Templates (12:41463) --}}
    <section class="relative bg-white px-4 pt-8 pb-10 lg:pt-[72px] lg:pb-[61px]" aria-labelledby="home-templates-title">
        <hr class="site-divider mx-auto mb-8 max-w-[343px] lg:hidden">
        <x-central::section-title id="home-templates-title" :title="__('content::site.home.templates_title')" />
        @include('content::central.partials.category-chips', ['categories' => $themeCategories, 'activeCategory' => null, 'class' => 'mt-6 lg:mt-8'])
        @if ($themes->isNotEmpty())
            <ul class="mx-auto mt-6 grid max-w-[1023px] grid-cols-2 gap-3 sm:gap-6 lg:mt-12 lg:grid-cols-3" data-reveal-stagger>
                @foreach ($themes->take(6) as $theme)
                    <li data-reveal="fade-up" data-reveal-delay="{{ $loop->index * 70 }}"><x-central::template-card :theme="$theme" /></li>
                @endforeach
            </ul>
            @if ($has('central.templates'))
                <div class="mt-6 text-center lg:mt-10">
                    <a href="{{ route('central.templates') }}" class="site-link-underline text-base text-primary lg:text-xl">{{ __('content::site.common.see_more') }}</a>
                </div>
            @endif
        @else
            <x-central::empty icon="layers" :title="__('content::site.templates.empty_title')" :text="__('content::site.templates.empty_text')" />
        @endif
        <span class="pointer-events-none absolute inset-x-0 bottom-0 h-[61px] bg-gradient-to-b from-white/0 to-white" aria-hidden="true"></span>
    </section>

    {{-- Categories (12:41486) --}}
    <section class="bg-white px-4 pt-8 pb-10 lg:pt-[72px] lg:pb-[72px]" aria-labelledby="home-categories-title">
        <x-central::section-title id="home-categories-title" :title="__('content::site.home.categories_title')" class="max-w-[679px]">
            <p class="text-base text-site-gray lg:mt-2 lg:text-xl">{!! __('content::site.home.categories_text', ['highlight' => '<span class="text-primary">'.e(__('content::site.home.categories_highlight')).'</span>']) !!}</p>
        </x-central::section-title>
        @if ($themeCategories->isNotEmpty())
            <ul class="mx-auto mt-6 grid max-w-[952px] grid-cols-4 gap-2 sm:gap-6 lg:mt-11 lg:gap-x-10 lg:gap-y-12">
                @foreach ($themeCategories->take(8) as $category)
                    @php $iconSlug = in_array($category->slug, $knownCategoryIcons, true) ? $category->slug : null; @endphp
                    <li data-reveal="zoom-in" data-reveal-delay="{{ $loop->index * 60 }}">
                        <a href="{{ $has('central.templates.category') ? route('central.templates.category', ['slug' => $category->slug]) : '#' }}" class="site-category group flex flex-col items-center gap-1 rounded-lg bg-site-tint p-1.5 pb-2 shadow-[0_4px_4px_rgba(183,151,151,0.25)] sm:gap-2 sm:rounded-2xl sm:p-4 sm:pb-4">
                            <span class="grid aspect-[176/184] w-full place-items-center overflow-hidden rounded-md bg-white sm:rounded-lg">
                                @if ($iconSlug)
                                    <img src="{{ $img('categories/'.$iconSlug.'.svg') }}" alt="" width="80" height="80" loading="lazy" class="site-category__icon size-8 sm:size-14 lg:size-20">
                                @else
                                    <x-ui::icon name="tag" :size="40" class="site-category__icon text-[#7b4236]" />
                                @endif
                            </span>
                            <span class="truncate text-sm font-medium text-[#7b4236] sm:text-lg lg:text-xl">{{ $category->name }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @else
            <x-central::empty icon="grid" :title="__('content::site.home.categories_empty')" />
        @endif
    </section>

    {{-- Services (12:41548) --}}
    <section class="site-services relative overflow-hidden bg-white px-4 pt-8 pb-10 lg:flex lg:h-[697px] lg:items-center lg:px-[88px] lg:py-0" aria-labelledby="home-services-title">
        <hr class="site-divider mx-auto mb-8 max-w-[343px] lg:hidden">
        <img src="{{ $img('home/services-person.webp') }}" alt="{{ __('content::site.home.services_image_alt') }}" width="864" height="1184" loading="lazy"
            class="absolute inset-y-0 end-0 hidden h-full w-[63%] object-cover object-[50%_35%] lg:block" data-parallax>
        <span class="site-services__fade absolute inset-y-0 start-0 hidden w-[71%] lg:block" aria-hidden="true"></span>
        <div class="relative">
            <h2 id="home-services-title" class="mb-4 text-center text-2xl font-medium text-site-ink lg:sr-only" data-reveal="fade-up">{{ __('content::site.home.services_title') }}</h2>
            <ul class="grid grid-cols-2 gap-4 lg:flex lg:w-[474px] lg:flex-col lg:gap-10">
                @foreach (['customize', 'setup', 'optimization', 'support'] as $key)
                    <li class="flex flex-col items-center gap-2 rounded-lg bg-site-tint px-2 py-4 text-center lg:flex-row lg:items-start lg:gap-4 lg:bg-transparent lg:p-0 lg:text-start" data-reveal="fade-start" data-reveal-delay="{{ $loop->index * 90 }}">
                        <img src="{{ $img('home/svc-'.$key.'.svg') }}" alt="" width="40" height="40" class="size-6 shrink-0 lg:size-10">
                        <div class="flex flex-col gap-1 lg:gap-3">
                            <h3 class="text-xs font-medium text-site-ink lg:text-xl">{{ __('content::site.home.services.'.$key.'.title') }}</h3>
                            <p class="text-[10px] text-site-gray lg:text-xl">{{ __('content::site.home.services.'.$key.'.text') }}</p>
                        </div>
                    </li>
                @endforeach
            </ul>
            @if ($has('central.pricing'))
                <a href="{{ route('central.pricing') }}" class="site-btn mt-12 hidden h-12 items-center justify-center rounded-2xl bg-primary px-8 text-base font-medium text-white hover:bg-primary-hover lg:inline-flex" data-reveal="fade-up" data-reveal-delay="380">{{ __('content::site.home.services_cta') }}</a>
            @endif
        </div>
    </section>

    {{-- Plans (12:41592) --}}
    @if ($plans->isNotEmpty())
        <section class="relative bg-white pt-8 pb-10 lg:pt-[72px] lg:pb-[72px]" aria-labelledby="home-plans-title"
            x-data="{ term: @js($term), setTerm(value) { this.term = value } }">
            <hr class="site-divider mx-auto mb-8 max-w-[343px] lg:hidden">
            <x-central::section-title id="home-plans-title" :title="__('content::site.home.plans_title')" class="px-4" />
            <div class="mt-4 flex justify-center px-4 lg:mt-8" data-reveal="fade-up"><x-central::term-toggle /></div>
            <div class="swiper site-rail site-plans mt-6 lg:mt-[88px]" data-rail data-rail-until="1024" data-rail-centered data-rail-initial="{{ $featuredIndex ?? 0 }}">
                <ul class="swiper-wrapper items-center lg:justify-center lg:gap-6">
                    @foreach ($plans as $plan)
                        <li class="swiper-slide !w-[216px] sm:!w-[308px] lg:!w-auto" data-reveal="fade-up" data-reveal-delay="{{ $loop->index * 90 }}">
                            <x-central::plan-card :plan="$plan" :term="$term" :featured="$loop->index === $featuredIndex" class="max-sm:[zoom:0.7]" />
                        </li>
                    @endforeach
                </ul>
            </div>
            <span class="pointer-events-none absolute inset-x-0 bottom-0 h-[136px] bg-gradient-to-b from-white/0 to-white" aria-hidden="true"></span>
        </section>
    @endif

    {{-- Customer reviews (12:41740) --}}
    @if ($testimonials->isNotEmpty())
        <section class="relative bg-white pt-8 pb-10 lg:pt-[72px] lg:pb-[42px]" aria-labelledby="home-reviews-title">
            <hr class="site-divider mx-auto mb-8 max-w-[343px] lg:hidden">
            <x-central::section-title id="home-reviews-title" :title="__('content::site.home.reviews_title')" class="px-4" />
            <div class="relative mx-auto mt-6 max-w-[1266px] lg:mt-[63px] lg:px-12" data-reveal="fade-up">
                <div class="swiper site-reviews" data-reviews aria-roledescription="carousel" aria-label="{{ __('content::site.home.reviews_title') }}">
                    <ul class="swiper-wrapper">
                        @foreach ($testimonials as $testimonial)
                            <li class="swiper-slide !h-auto !w-[300px] sm:!w-[374px]">
                                <figure class="flex h-full min-h-[226px] flex-col items-center gap-3 rounded-[32px] bg-site-tint px-4 py-5 text-center">
                                    <img src="{{ $img('home/quote.svg') }}" alt="" width="40" height="40" class="size-10 rtl:-scale-x-100">
                                    <blockquote class="text-sm tracking-[0.5px] text-[#707070]"><p>{{ $testimonial['quote'] }}</p></blockquote>
                                    <figcaption class="mt-auto text-xs text-site-gray"><span class="font-medium text-site-ink">{{ $testimonial['name'] }}</span> · {{ $testimonial['role'] }}</figcaption>
                                </figure>
                            </li>
                        @endforeach
                    </ul>
                </div>
                <button type="button" class="site-arrow absolute start-0 top-1/2 hidden -translate-y-1/2 lg:grid" data-reviews-prev aria-label="{{ __('content::site.common.previous') }}"><x-ui::icon name="chevron-left" :size="32" class="rtl:-scale-x-100" /></button>
                <button type="button" class="site-arrow absolute end-0 top-1/2 hidden -translate-y-1/2 lg:grid" data-reviews-next aria-label="{{ __('content::site.common.next') }}"><x-ui::icon name="chevron-right" :size="32" class="rtl:-scale-x-100" /></button>
                <div class="site-bullets mt-8 flex justify-center gap-2 lg:mt-[58px]" data-reviews-pagination></div>
            </div>
        </section>
    @endif

    {{-- Blogs (12:41768) --}}
    @if ($posts->isNotEmpty())
        <section class="site-blogs relative pt-8 pb-12 lg:pt-[72px] lg:pb-[72px]" aria-labelledby="home-blogs-title">
            <hr class="site-divider mx-auto mb-8 max-w-[343px] lg:hidden">
            <x-central::section-title id="home-blogs-title" :title="__('content::site.home.blogs_title')" class="px-4" />
            <div class="swiper site-rail mt-6 lg:mt-12" data-rail data-rail-until="1024" data-rail-centered data-rail-initial="{{ $posts->count() > 1 ? 1 : 0 }}">
                <ul class="swiper-wrapper lg:justify-center lg:gap-6">
                    @foreach ($posts->take(3) as $post)
                        <li class="swiper-slide !h-auto !w-[264px] sm:!w-[340px] lg:!w-[397px]" data-reveal="fade-up" data-reveal-delay="{{ $loop->index * 90 }}">
                            <x-central::blog-card :post="$post" class="shadow-[0_4px_24px_rgba(65,37,30,0.08)]" />
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif
</x-central::layout>
