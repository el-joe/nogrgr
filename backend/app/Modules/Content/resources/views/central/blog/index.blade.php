{{--
    Blog list (Figma 127:11911, mobile 127:12095): "Our Blog" heading, search, category
    chips, featured post (first post of page 1 without filters), 3-column card grid and
    pagination. Data: $seo, $posts (LengthAwarePaginator<BlogPost>), $categories,
    $activeCategory (?BlogCategory), $search (?string).
--}}
@php
    $search = trim((string) ($search ?? request('q', '')));
    $activeCategory ??= null;
    $items = collect($posts->items());
    $featured = $posts->currentPage() === 1 && ! $activeCategory && $search === '' ? $items->first() : null;
    $grid = $featured ? $items->slice(1) : $items;
    $indexUrl = route('central.blog.index');
@endphp
<x-central::layout :seo="$seo" page="site-blog">
    <span class="site-glow site-glow--page" aria-hidden="true"></span>
    <section class="relative mx-auto max-w-[1264px] px-4 pt-6 pb-16 lg:px-[85px] lg:pt-[33px] lg:pb-[72px]" aria-labelledby="blog-title">
        <div class="flex flex-col items-center gap-3 text-center" data-reveal="fade-up">
            <h1 id="blog-title" class="text-[28px] font-bold text-site-ink lg:text-[40px]">{!! __('content::site.blog.heading_html', ['highlight' => '<span class="text-primary">'.e(__('content::site.blog.heading_highlight')).'</span>']) !!}</h1>
            <p class="text-sm text-site-gray lg:text-base">{{ __('content::site.blog.text') }}</p>
            <form method="GET" action="{{ $indexUrl }}" role="search" class="mt-2 w-full max-w-[520px]">
                @if ($activeCategory)<input type="hidden" name="category" value="{{ $activeCategory->slug }}">@endif
                <label for="blog-search" class="sr-only">{{ __('content::site.blog.search') }}</label>
                <div class="site-field flex h-10 items-center gap-2 rounded-lg border border-site-border bg-white px-3 lg:h-[46px]">
                    <x-ui::icon name="search" :size="18" class="shrink-0 text-site-gray" />
                    <input id="blog-search" type="search" name="q" value="{{ $search }}" placeholder="{{ __('content::site.blog.search') }}" class="min-w-0 flex-1 bg-transparent text-sm text-site-ink outline-none placeholder:text-site-ink">
                    <button type="submit" class="sr-only">{{ __('content::site.blog.search_submit') }}</button>
                </div>
            </form>
        </div>

        @if (collect($categories ?? [])->isNotEmpty())
            <nav class="mt-5 flex justify-center" aria-label="{{ __('content::site.blog.categories') }}" data-reveal="fade-up" data-reveal-delay="80">
                <div class="swiper site-chips max-w-full" data-chips>
                    <ul class="swiper-wrapper sm:justify-center">
                        <li class="swiper-slide !w-auto">
                            <a href="{{ $indexUrl }}{{ $search !== '' ? '?q='.urlencode($search) : '' }}" @class(['site-chip h-8 border border-[#eee] px-4 text-xs', 'is-active' => ! $activeCategory]) @if (! $activeCategory) aria-current="page" @endif draggable="false">{{ __('content::site.blog.all') }}</a>
                        </li>
                        @foreach ($categories as $category)
                            @php $active = $activeCategory?->slug === $category->slug; @endphp
                            <li class="swiper-slide !w-auto ms-2">
                                <a href="{{ route('central.blog.index', array_filter(['category' => $category->slug, 'q' => $search ?: null])) }}" @class(['site-chip h-8 border border-[#eee] px-4 text-xs', 'is-active' => $active, 'bg-white text-site-ink' => ! $active]) @if ($active) aria-current="page" @endif draggable="false">{{ $category->name }}</a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </nav>
        @endif

        @if ($search !== '')
            <p class="mt-6 text-center text-sm text-site-gray">{{ __('content::site.blog.results_for', ['term' => $search]) }}</p>
        @endif

        @if ($items->isEmpty())
            <x-central::empty icon="file" :title="__('content::site.blog.empty_title')" :text="__('content::site.blog.empty_text')"
                :action-url="($activeCategory || $search !== '') ? $indexUrl : null" :action-label="__('content::site.blog.empty_cta')" />
        @else
            @if ($featured)
                @php $cover = $featured->coverSources(); @endphp
                <article class="site-featured site-blog-card group relative mt-8 grid overflow-hidden rounded-3xl bg-white shadow-[0_8px_40px_rgba(65,37,30,0.08)] lg:mt-10 lg:grid-cols-[minmax(0,454px)_1fr] lg:min-h-[256px]" data-reveal="fade-up">
                    <div class="aspect-[16/9] overflow-hidden bg-site-tint lg:aspect-auto">
                        @if ($cover['src'] ?? $cover['webp'] ?? null)
                            <picture>
                                @if (! empty($cover['avif']))<source srcset="{{ $cover['avif'] }}" type="image/avif">@endif
                                @if (! empty($cover['webp']))<source srcset="{{ $cover['webp'] }}" type="image/webp">@endif
                                <img src="{{ $cover['src'] ?? $cover['webp'] }}" alt="{{ __('content::site.blog.cover_alt', ['title' => $featured->title]) }}" width="908" height="512" fetchpriority="high" class="site-zoom size-full object-cover">
                            </picture>
                        @endif
                    </div>
                    <div class="flex flex-col justify-center gap-3 p-5 lg:px-6 lg:py-8">
                        <span class="self-start rounded-full bg-primary px-3 py-1 text-[11px] text-white">{{ __('content::site.blog.featured') }}@if ($featured->category) · {{ $featured->category->name }}@endif</span>
                        <h2 class="text-xl leading-snug font-medium text-site-ink lg:text-[28px]">
                            <a href="{{ $featured->url() }}" class="site-stretched after:absolute after:inset-0 focus-visible:outline-none">{{ $featured->title }}</a>
                        </h2>
                        <p class="line-clamp-3 text-sm text-site-gray">{{ $featured->excerpt }}</p>
                        <p class="text-xs text-site-gray">
                            @if (! empty($featured->author_name)){{ $featured->author_name }} · @endif
                            @if ($featured->published_at)<time datetime="{{ $featured->published_at->toDateString() }}">{{ $featured->published_at->locale(app()->getLocale())->translatedFormat('j M Y') }}</time> · @endif{{ __('content::site.common.min_read', ['count' => $featured->reading_minutes]) }}
                        </p>
                    </div>
                </article>
            @endif

            @if ($grid->isNotEmpty())
                <ul class="mt-6 grid gap-4 sm:grid-cols-2 lg:mt-6 lg:grid-cols-3 lg:gap-x-4 lg:gap-y-6">
                    @foreach ($grid as $post)
                        <li data-reveal="fade-up" data-reveal-delay="{{ ($loop->index % 3) * 80 }}">
                            <x-central::blog-card :post="$post" variant="list" class="shadow-[0_8px_30px_rgba(65,37,30,0.08)]" />
                        </li>
                    @endforeach
                </ul>
            @endif

            @if ($posts->hasPages())
                <div class="mt-10 flex justify-center">
                    @include('content::central.partials.pagination', ['paginator' => $posts->withQueryString()])
                </div>
            @endif
        @endif
    </section>
</x-central::layout>
