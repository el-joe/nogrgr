{{--
    Storefront motion gallery (/admin/ui-kit/storefront-motion?family=…&dir=…): every
    module of resources/js/storefront/core/motion, rendered with the chosen family's
    storefront layout and bundle so the per-family motion tokens apply. Sample content
    from App\Modules\Platform\Support\MotionDemoSamples (ComfyUI imagery).
--}}
@php
    $t = fn (string $key, array $replace = []) => __('platform::motion-demo.'.$key, $replace);
    $money = fn (int $amount) => 'USD '.\Illuminate\Support\Number::format($amount, 2, locale: \App\Shared\Support\Locale::numberLocale());
    $chunk = fn (int $offset) => array_slice(array_merge($products, $products), $offset, 4);
@endphp
<x-layouts.storefront :family="$family" :title="$t('title')">
    {{-- Sticky header: shrink + shadow on scroll, hides on scroll down below 1024px --}}
    <header data-sticky-header class="border-b border-line bg-surface">
        <div class="mx-auto flex h-16 max-w-7xl items-center gap-4 px-4">
            <a href="{{ route('admin.ui-kit') }}" data-header-shrink class="text-lg font-semibold text-ink">{{ $t('store') }}</a>
            <nav aria-label="{{ $t('title') }}" class="hidden items-center gap-5 text-sm text-ink-muted lg:flex">
                @foreach (['hero', 'rail', 'spotlight', 'tabs', 'gallery', 'cart'] as $anchor)
                    <a href="#{{ $anchor }}" class="hover:text-ink">{{ $t('nav.'.$anchor) }}</a>
                @endforeach
            </nav>
            <div class="ms-auto flex items-center gap-2">
                <a href="#cart" data-cart-target class="relative grid grid-cols-1 size-10 place-items-center rounded-full text-ink hover:bg-surface-sunken" aria-label="{{ $t('cart') }}">
                    <x-ui::icon name="cart" :size="22" />
                    <span data-cart-count class="absolute -end-0.5 -top-0.5 grid grid-cols-1 h-5 min-w-5 place-items-center rounded-full bg-primary px-1 text-[11px] font-semibold text-on-primary" aria-label="{{ $t('cart_count') }}">2</span>
                </a>
            </div>
        </div>
    </header>

    <main class="mx-auto grid grid-cols-1 max-w-7xl gap-14 px-4 pb-24 pt-6">
        {{-- QA toolbar --}}
        <section class="grid grid-cols-1 gap-4 rounded-card border border-line p-4 sm:p-6" data-reveal="fade-up">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h1 class="text-2xl font-semibold text-ink">{{ $t('title') }}</h1>
                <a href="{{ route('admin.ui-kit') }}" class="text-sm text-primary underline-offset-2 hover:underline">{{ $t('back') }}</a>
            </div>
            <p class="max-w-3xl text-sm text-ink-muted">{{ $t('intro') }}</p>
            <div class="flex flex-wrap gap-4 text-sm">
                <div class="flex flex-wrap items-center gap-2" role="group" aria-label="{{ $t('family') }}">
                    <span class="text-ink-muted">{{ $t('family') }}:</span>
                    @foreach ($families as $option)
                        <a href="{{ request()->fullUrlWithQuery(['family' => $option]) }}" @class(['mo-press rounded-full border px-3 py-1 capitalize', 'border-primary bg-primary text-on-primary' => $option === $family, 'border-line text-ink' => $option !== $family]) @if ($option === $family) aria-current="true" @endif>{{ $option }}</a>
                    @endforeach
                </div>
                <div class="flex flex-wrap items-center gap-2" role="group" aria-label="{{ $t('direction') }}">
                    <span class="text-ink-muted">{{ $t('direction') }}:</span>
                    @foreach (['ltr' => 'LTR · English', 'rtl' => 'RTL · العربية'] as $option => $label)
                        <a href="{{ request()->fullUrlWithQuery(['dir' => $option]) }}" @class(['mo-press rounded-full border px-3 py-1', 'border-primary bg-primary text-on-primary' => $option === $direction, 'border-line text-ink' => $option !== $direction]) @if ($option === $direction) aria-current="true" @endif>{{ $label }}</a>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- Hero: fade + Ken Burns, autoplay with progress, pause control --}}
        <section id="hero" aria-roledescription="carousel" aria-label="{{ $t('nav.hero') }}">
            <div class="swiper mo-hero relative overflow-hidden rounded-card" data-hero='{"delay":6000}'>
                <div class="swiper-wrapper">
                    @foreach ($heroes as $hero)
                        <div class="swiper-slide relative aspect-[4/5] overflow-hidden sm:aspect-[12/5]">
                            <div class="mo-media absolute inset-0" style="--placeholder: url('{{ $hero['image']['placeholder'] }}')">
                                <img src="{{ $hero['image']['src'] }}" width="{{ $hero['image']['width'] }}" height="{{ $hero['image']['height'] }}" alt="" @if (! $loop->first) loading="lazy" @endif decoding="async" draggable="false" data-blur-up class="mo-kenburns h-full w-full object-contain bg-media">
                            </div>
                            <div class="absolute inset-0 bg-gradient-to-t from-black/55 via-black/10 to-transparent sm:bg-gradient-to-r sm:rtl:bg-gradient-to-l"></div>
                            <div data-hero-copy class="absolute inset-x-6 bottom-20 grid grid-cols-1 max-w-md gap-3 text-white sm:inset-x-12 sm:bottom-auto sm:top-1/2 sm:-translate-y-1/2">
                                <p class="text-xs font-semibold uppercase tracking-[0.2em]">{{ $t('hero.'.$hero['key'].'.eyebrow') }}</p>
                                <h2 class="text-3xl font-semibold leading-tight sm:text-4xl">{{ $t('hero.'.$hero['key'].'.title') }}</h2>
                                <p><a href="#rail" class="mo-press inline-flex h-11 items-center rounded-full bg-white px-6 text-sm font-medium text-ink">{{ $t('hero.'.$hero['key'].'.cta') }}</a></p>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="absolute bottom-4 end-4 z-10 flex items-center gap-2 sm:bottom-6 sm:end-6">
                    <button type="button" data-motion-prev class="mo-arrow mo-press grid grid-cols-1 size-10 place-items-center rounded-full bg-white/90 text-ink" aria-label="{{ $t('previous') }}"><x-ui::icon name="chevron-left" :size="18" /></button>
                    <button type="button" data-motion-pause aria-pressed="false" data-state="playing" class="mo-press grid grid-cols-1 size-10 place-items-center rounded-full bg-white/90 text-ink" aria-label="{{ $t('pause') }}">
                        <svg class="mo-icon-pause" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><rect x="6" y="5" width="4" height="14" rx="1"/><rect x="14" y="5" width="4" height="14" rx="1"/></svg>
                        <svg class="mo-icon-play" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg>
                    </button>
                    <button type="button" data-motion-next class="mo-arrow mo-press grid grid-cols-1 size-10 place-items-center rounded-full bg-white/90 text-ink" aria-label="{{ $t('next') }}"><x-ui::icon name="chevron-right" :size="18" /></button>
                </div>
                <div data-hero-progress class="mo-progress absolute inset-x-6 top-4 z-10 bg-white/30 sm:inset-x-12"><span class="mo-progress-fill bg-white"></span></div>
            </div>
        </section>

        {{-- Marquee trust strip: infinite, draggable, pausable --}}
        <section aria-label="{{ $t('marquee.label') }}">
            <div class="mo-marquee relative rounded-full border border-line bg-surface-sunken py-3" data-marquee='{"speed":36}'>
                <div class="mo-marquee-lane">
                    <ul data-marquee-track class="flex items-center gap-10 pe-10 text-sm font-medium text-ink">
                        @foreach (['shipping' => 'truck', 'returns' => 'return', 'secure' => 'shield', 'cod' => 'wallet', 'support' => 'lifebuoy', 'gift' => 'gift'] as $benefit => $icon)
                            <li class="flex items-center gap-2 whitespace-nowrap"><x-ui::icon :name="$icon" :size="18" class="text-primary" />{{ $t('marquee.'.$benefit) }}</li>
                        @endforeach
                    </ul>
                </div>
                <button type="button" data-motion-pause aria-pressed="false" data-state="playing" class="mo-press absolute end-2 top-1/2 z-10 grid grid-cols-1 size-8 -translate-y-1/2 place-items-center rounded-full bg-surface text-ink shadow-card" aria-label="{{ $t('pause') }}">
                    <svg class="mo-icon-pause" width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><rect x="6" y="5" width="4" height="14" rx="1"/><rect x="14" y="5" width="4" height="14" rx="1"/></svg>
                    <svg class="mo-icon-play" width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg>
                </button>
            </div>
        </section>

        {{-- Product rail: free mode + snap, arrows, progress --}}
        <section id="rail" class="grid grid-cols-1 gap-4" data-reveal="fade-up">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-semibold text-ink">{{ $t('rail.title') }}</h2>
                    <p class="text-sm text-ink-muted">{{ $t('rail.subtitle') }}</p>
                </div>
                <div class="flex gap-2">
                    <button type="button" data-motion-prev class="mo-arrow mo-press grid grid-cols-1 size-10 place-items-center rounded-full border border-line text-ink" aria-label="{{ $t('previous') }}"><x-ui::icon name="chevron-left" :size="18" /></button>
                    <button type="button" data-motion-next class="mo-arrow mo-press grid grid-cols-1 size-10 place-items-center rounded-full border border-line text-ink" aria-label="{{ $t('next') }}"><x-ui::icon name="chevron-right" :size="18" /></button>
                </div>
                <div class="swiper mo-rail -mx-4 w-[calc(100%+2rem)] basis-full px-4" data-rail>
                    <div class="swiper-wrapper">
                        @foreach ($products as $item)
                            <div class="swiper-slide !w-[200px] py-2 sm:!w-[240px]">
                                @include('platform::admin.storefront-motion._card', ['product' => $item])
                            </div>
                        @endforeach
                    </div>
                    <div data-motion-pagination class="mo-progress mt-4"></div>
                </div>
            </div>
        </section>

        {{-- Spotlight (center + left, old-system-docs/home-pages-custom-sliders.md) --}}
        <section id="spotlight" class="grid grid-cols-1 gap-10" data-reveal="fade-up">
            @foreach (['center', 'left'] as $layout)
                <div class="grid grid-cols-1 gap-3">
                    <div class="flex flex-wrap items-end justify-between gap-4">
                        <div>
                            <h2 class="text-2xl font-semibold text-ink">{{ $t('spotlight.'.$layout) }}</h2>
                            <p class="text-sm text-ink-muted">{{ $t('spotlight.hint') }}</p>
                        </div>
                        <div class="flex gap-2">
                            <button type="button" data-motion-prev class="mo-arrow mo-press grid grid-cols-1 size-10 place-items-center rounded-full border border-line text-ink" aria-label="{{ $t('previous') }}"><x-ui::icon name="chevron-left" :size="18" /></button>
                            <button type="button" data-motion-next class="mo-arrow mo-press grid grid-cols-1 size-10 place-items-center rounded-full border border-line text-ink" aria-label="{{ $t('next') }}"><x-ui::icon name="chevron-right" :size="18" /></button>
                        </div>
                        <div class="swiper mo-spotlight basis-full" data-spotlight='{"layout":"{{ $layout }}"}' aria-roledescription="carousel" aria-label="{{ $t('spotlight.'.$layout) }}">
                            <div class="swiper-wrapper">
                                @foreach ($products as $item)
                                    <div class="swiper-slide">
                                        <div class="relative overflow-hidden rounded-card bg-surface">
                                            <div class="mo-media aspect-[4/5]" style="--placeholder: url('{{ $item['image']['placeholder'] }}')">
                                                <img src="{{ $item['image']['src'] }}" width="{{ $item['image']['width'] }}" height="{{ $item['image']['height'] }}" alt="{{ $item['name'] }}" loading="lazy" decoding="async" draggable="false" data-blur-up class="h-full w-full object-contain bg-media">
                                            </div>
                                            <div class="absolute inset-x-0 bottom-0 grid grid-cols-1 gap-0.5 bg-gradient-to-t from-black/60 to-transparent p-4 pt-10 text-white">
                                                <p class="text-sm font-medium">{{ $item['name'] }}</p>
                                                <p class="text-sm font-semibold" dir="ltr">{{ $money($item['price']) }}</p>
                                            </div>
                                            <span class="mo-spotlight-shadow pointer-events-none absolute inset-0 rounded-card ring-2 ring-primary" aria-hidden="true"></span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </section>

        {{-- Tabs that swipe on touch --}}
        <section id="tabs" class="grid grid-cols-1 gap-4" data-tabs-swipe data-reveal="fade-up">
            <h2 class="text-2xl font-semibold text-ink">{{ $t('tabs.title') }}</h2>
            <div role="tablist" aria-label="{{ $t('tabs.title') }}" class="flex gap-2 overflow-x-auto">
                @foreach (['new', 'best', 'sale'] as $tab)
                    <button type="button" role="tab" id="tab-{{ $tab }}" aria-controls="panel-{{ $tab }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}" class="mo-press whitespace-nowrap rounded-full border border-line px-4 py-2 text-sm text-ink aria-selected:border-primary aria-selected:bg-primary aria-selected:text-on-primary">{{ $t('tabs.'.$tab) }}</button>
                @endforeach
            </div>
            <div class="swiper">
                <div class="swiper-wrapper">
                    @foreach (['new' => 0, 'best' => 2, 'sale' => 4] as $tab => $offset)
                        <div class="swiper-slide" role="tabpanel" id="panel-{{ $tab }}" aria-labelledby="tab-{{ $tab }}">
                            <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                                @foreach ($chunk($offset) as $item)
                                    @include('platform::admin.storefront-motion._card', ['product' => $item])
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- Flash sale countdown (flip digits) --}}
        <section class="grid grid-cols-1 items-center gap-6 rounded-card bg-primary p-6 text-on-primary sm:grid-cols-[minmax(0,1fr)_auto] sm:p-10" data-reveal="zoom">
            <div class="grid grid-cols-1 gap-2">
                <h2 class="text-3xl font-semibold">{{ $t('flash.title') }}</h2>
                <p class="opacity-90">{{ $t('flash.subtitle') }}</p>
                <p><a href="#rail" class="mo-press mt-2 inline-flex h-11 items-center rounded-full bg-surface px-6 text-sm font-medium text-ink">{{ $t('flash.cta') }}</a></p>
            </div>
            <div data-countdown="{{ $flashEndsAt }}" data-countdown-label="{{ $t('flash.ends') }}" role="timer" class="grid grid-cols-1 gap-2">
                <p class="text-sm font-medium opacity-90">{{ $t('flash.ends') }}</p>
                <div class="flex gap-2" dir="ltr">
                    @foreach (['days', 'hours', 'minutes', 'seconds'] as $unit)
                        <div class="grid grid-cols-1 w-16 place-items-center gap-1 rounded-control bg-black/15 py-2">
                            <span data-unit="{{ $unit }}" class="text-2xl font-semibold">00</span>
                            <span class="text-[11px] uppercase opacity-80">{{ $t('flash.'.$unit) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- Product gallery: swipe, zoom, thumbnails sync, shared-element target --}}
        <section id="gallery" class="grid grid-cols-1 gap-8 lg:grid-cols-2" data-reveal="fade-up">
            <div data-gallery class="grid grid-cols-1 min-w-0 gap-3">
                <div class="swiper w-full overflow-hidden rounded-card bg-surface-sunken" data-gallery-main tabindex="0" aria-label="{{ $t('gallery.title') }}">
                    <div class="swiper-wrapper">
                        @foreach ($gallery as $image)
                            <div class="swiper-slide">
                                <div class="swiper-zoom-container aspect-[4/5]">
                                    <img src="{{ $image['src'] }}" width="{{ $image['width'] }}" height="{{ $image['height'] }}" alt="{{ $product['name'] }}" @if (! $loop->first) loading="lazy" @else data-vt-name="product-{{ $product['slug'] }}" @endif decoding="async" draggable="false" class="h-full w-full object-contain bg-media">
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <button type="button" data-motion-prev class="mo-arrow mo-press absolute start-3 top-1/2 z-10 grid grid-cols-1 size-10 -translate-y-1/2 place-items-center rounded-full bg-surface/90 text-ink" aria-label="{{ $t('previous') }}"><x-ui::icon name="chevron-left" :size="18" /></button>
                    <button type="button" data-motion-next class="mo-arrow mo-press absolute end-3 top-1/2 z-10 grid grid-cols-1 size-10 -translate-y-1/2 place-items-center rounded-full bg-surface/90 text-ink" aria-label="{{ $t('next') }}"><x-ui::icon name="chevron-right" :size="18" /></button>
                </div>
                <div class="swiper w-full" data-gallery-thumbs aria-label="{{ $t('gallery.thumbnails') }}">
                    <div class="swiper-wrapper">
                        @foreach ($gallery as $image)
                            <div class="swiper-slide !w-20 cursor-pointer overflow-hidden rounded-control border-2 border-transparent opacity-60 transition-opacity [&.is-active]:border-primary [&.is-active]:opacity-100">
                                <img src="{{ $image['thumb'] ?? $image['src'] }}" width="128" height="160" alt="" loading="lazy" draggable="false" class="aspect-[4/5] w-full object-contain bg-media">
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
            <div class="grid grid-cols-1 content-start gap-4" data-product="{{ $product['slug'] }}">
                <p class="text-sm uppercase tracking-wide text-ink-muted">{{ $product['category'] }}</p>
                <h2 class="text-3xl font-semibold text-ink">{{ $product['name'] }}</h2>
                <p class="text-2xl font-semibold text-ink" dir="ltr">{{ $money($product['price']) }}</p>
                <p class="text-ink-muted">{{ $t('gallery.description') }}</p>
                <p class="text-sm text-ink-muted">{{ $t('gallery.zoom') }}</p>
                <img src="{{ $product['image']['src'] }}" alt="" aria-hidden="true" data-fly-image class="sr-only" width="64" height="80">
                <div class="flex gap-3">
                    <button type="button" data-add-to-cart class="mo-press flex h-12 flex-1 items-center justify-center gap-2 rounded-full bg-primary px-6 font-medium text-on-primary">
                        <span class="mo-state mo-state-idle items-center gap-2"><x-ui::icon name="cart" :size="18" />{{ $t('card.add') }}</span>
                        <span class="mo-state mo-state-loading items-center gap-2"><x-ui::icon name="refresh" :size="18" class="mo-spin" />{{ $t('card.adding') }}</span>
                        <span class="mo-state mo-state-done items-center gap-2"><x-ui::icon name="check" :size="18" />{{ $t('card.added') }}</span>
                    </button>
                    <button type="button" data-favorite aria-pressed="false" class="mo-press grid grid-cols-1 size-12 place-items-center rounded-full border border-line text-primary" aria-label="{{ $t('card.favorite', ['name' => $product['name']]) }}">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path class="mo-heart-fill" d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1-1.1a5.5 5.5 0 0 0-7.8 7.8l1 1.1L12 21l7.8-7.5 1-1.1a5.5 5.5 0 0 0 0-7.8z"/></svg>
                    </button>
                </div>
            </div>
        </section>

        {{-- Parallax banner + count-up stats --}}
        <section class="relative overflow-hidden rounded-card" aria-labelledby="stats-title">
            <div class="mo-media absolute inset-0" style="--placeholder: url('{{ $heroes[1]['image']['placeholder'] }}')">
                <img src="{{ $heroes[1]['image']['src'] }}" width="{{ $heroes[1]['image']['width'] }}" height="{{ $heroes[1]['image']['height'] }}" alt="" loading="lazy" decoding="async" data-blur-up data-parallax="0.08" class="h-full w-full object-contain bg-media">
            </div>
            <div class="relative grid grid-cols-1 gap-6 bg-black/45 p-8 text-white sm:p-14">
                <h2 id="stats-title" class="text-3xl font-semibold">{{ $t('stats.title') }}</h2>
                <dl class="grid gap-6 sm:grid-cols-3" dir="ltr">
                    @foreach (['customers' => [12400, '+'], 'rating' => [48, ''], 'delivery' => [48, '']] as $stat => [$value, $suffix])
                        <div>
                            <dt class="sr-only">{{ $t('stats.'.$stat) }}</dt>
                            <dd class="text-4xl font-semibold"><span data-count-to="{{ $value }}" data-count-suffix="{{ $suffix }}">{{ \Illuminate\Support\Number::format($value, locale: \App\Shared\Support\Locale::numberLocale()) }}{{ $suffix }}</span></dd>
                            <dd class="text-sm opacity-85" dir="auto">{{ $t('stats.'.$stat) }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>
        </section>

        {{-- Cart: swipe-to-remove + undo, bottom sheets --}}
        <section id="cart" class="grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,1fr)_320px]" data-reveal="fade-up">
            <div class="grid grid-cols-1 content-start gap-3">
                <h2 class="text-2xl font-semibold text-ink">{{ $t('cart_section.title') }}</h2>
                <p class="text-sm text-ink-muted">{{ $t('cart_section.hint') }}</p>
                <ul class="grid grid-cols-1 gap-2" role="list">
                    @foreach (array_slice($products, 0, 3) as $item)
                        <li data-swipe-remove data-remove-label="{{ $item['name'] }}" class="rounded-card border border-line">
                            <div class="mo-swipe-bg" aria-hidden="true"><x-ui::icon name="trash" :size="20" /></div>
                            <div data-swipe-content class="flex items-center gap-4 bg-surface p-3">
                                <img src="{{ $item['image']['thumb'] ?? $item['image']['src'] }}" width="64" height="80" alt="" loading="lazy" draggable="false" class="h-20 w-16 rounded-control object-contain bg-media">
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium text-ink">{{ $item['name'] }}</p>
                                    <p class="text-xs text-ink-muted">{{ $t('cart_section.quantity', ['count' => 1]) }}</p>
                                </div>
                                <p class="text-sm font-semibold text-ink" dir="ltr">{{ $money($item['price']) }}</p>
                                <button type="button" data-swipe-remove-button class="mo-press rounded-full px-3 py-1.5 text-sm text-ink-muted hover:text-danger">{{ $t('cart_section.remove') }}</button>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
            <div class="grid grid-cols-1 content-start gap-3 rounded-card border border-line p-4">
                <button type="button" data-sheet-open="sheet-filters" class="mo-press flex h-11 items-center justify-center gap-2 rounded-full border border-line text-sm text-ink"><x-ui::icon name="filter" :size="16" />{{ $t('cart_section.filters') }}</button>
                <button type="button" data-sheet-open="sheet-sort" class="mo-press flex h-11 items-center justify-center gap-2 rounded-full border border-line text-sm text-ink"><x-ui::icon name="sort" :size="16" />{{ $t('cart_section.sort') }}</button>
                <a href="#cart" class="mo-press flex h-11 items-center justify-center rounded-full bg-primary text-sm font-medium text-on-primary">{{ $t('cart_section.checkout') }}</a>
            </div>
        </section>

        {{-- Loading: skeleton shimmer --}}
        <section class="grid grid-cols-1 gap-4" aria-busy="true" aria-labelledby="loading-title">
            <div>
                <h2 id="loading-title" class="text-2xl font-semibold text-ink">{{ $t('loading.title') }}</h2>
                <p class="text-sm text-ink-muted">{{ $t('loading.hint') }}</p>
            </div>
            <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                @for ($index = 0; $index < 4; $index++)
                    <div class="grid grid-cols-1 gap-3" aria-hidden="true">
                        <div class="mo-skeleton aspect-[4/5] rounded-card"></div>
                        <div class="mo-skeleton h-3 w-2/3 rounded-full"></div>
                        <div class="mo-skeleton h-3 w-1/3 rounded-full"></div>
                    </div>
                @endfor
            </div>
        </section>

        {{-- Staggered reveal grid --}}
        <section class="grid grid-cols-1 gap-4" aria-labelledby="grid-title">
            <div>
                <h2 id="grid-title" class="text-2xl font-semibold text-ink">{{ $t('grid.title') }}</h2>
                <p class="text-sm text-ink-muted">{{ $t('grid.hint') }}</p>
            </div>
            <div class="grid grid-cols-2 gap-4 lg:grid-cols-4" data-reveal-stagger>
                @foreach ($products as $item)
                    <div data-reveal="fade-up">
                        @include('platform::admin.storefront-motion._card', ['product' => $item])
                    </div>
                @endforeach
            </div>
        </section>
    </main>

    {{-- Bottom sheets (drag the grabber down to close) --}}
    <div class="mo-sheet" id="sheet-filters" data-sheet role="dialog" aria-modal="true" aria-labelledby="sheet-filters-title" hidden>
        <div class="mo-sheet-scrim" data-sheet-close></div>
        <div class="mo-sheet-panel">
            <div data-sheet-handle class="cursor-grab px-5 pb-3">
                <span class="mo-sheet-grabber" aria-hidden="true"></span>
                <div class="flex items-center justify-between gap-3">
                    <h2 id="sheet-filters-title" class="text-lg font-semibold text-ink">{{ $t('sheet.filters') }}</h2>
                    <button type="button" data-sheet-close class="mo-press grid grid-cols-1 size-9 place-items-center rounded-full text-ink-muted" aria-label="{{ $t('sheet.close') }}"><x-ui::icon name="x" :size="18" /></button>
                </div>
                <p class="sr-only">{{ $t('sheet.drag_hint') }}</p>
            </div>
            <div class="grid grid-cols-1 gap-5 px-5 pb-4">
                <fieldset class="grid grid-cols-1 gap-2">
                    <legend class="mb-2 text-sm font-medium text-ink">{{ $t('sheet.categories') }}</legend>
                    @foreach (array_unique(array_column($products, 'category')) as $category)
                        <label class="flex items-center gap-3 text-sm text-ink"><input type="checkbox" class="size-4 accent-[var(--color-primary)]"> {{ $category }}</label>
                    @endforeach
                </fieldset>
                <fieldset class="grid grid-cols-1 gap-2">
                    <legend class="mb-2 text-sm font-medium text-ink">{{ $t('sheet.price') }}</legend>
                    @foreach (['under_50', '50_150', 'over_150'] as $range)
                        <label class="flex items-center gap-3 text-sm text-ink"><input type="radio" name="price" class="size-4 accent-[var(--color-primary)]"> {{ $t('sheet.'.$range) }}</label>
                    @endforeach
                </fieldset>
                <button type="button" data-sheet-close class="mo-press h-12 rounded-full bg-primary font-medium text-on-primary">{{ $t('sheet.apply') }}</button>
            </div>
        </div>
    </div>

    <div class="mo-sheet" id="sheet-sort" data-sheet data-sheet-mode="always" role="dialog" aria-modal="true" aria-labelledby="sheet-sort-title" hidden>
        <div class="mo-sheet-scrim" data-sheet-close></div>
        <div class="mo-sheet-panel mx-auto max-w-lg">
            <div data-sheet-handle class="cursor-grab px-5 pb-3">
                <span class="mo-sheet-grabber" aria-hidden="true"></span>
                <h2 id="sheet-sort-title" class="text-lg font-semibold text-ink">{{ $t('sheet.sort') }}</h2>
            </div>
            <ul class="grid grid-cols-1 px-3 pb-2" role="list">
                @foreach (['popular', 'newest', 'price_low', 'price_high'] as $sort)
                    <li><button type="button" data-sheet-close class="flex h-12 w-full items-center rounded-control px-3 text-start text-sm text-ink hover:bg-surface-sunken">{{ $t('sheet.'.$sort) }}</button></li>
                @endforeach
            </ul>
        </div>
    </div>
</x-layouts.storefront>
