{{--
    Blog post (Figma 127:12209, mobile 127:12370): breadcrumbs, title, author/date
    meta, cover, article body, share row (copy + networks), related articles and the
    newsletter band. Data: $seo, $post, $related (≤3), $shareLinks (network => url).
--}}
@php
    $cover = $post->coverSources();
    $related = collect($related ?? []);
    $shareLinks = collect($shareLinks ?? []);
    $author = $post->author_name ?? null;
    $initials = $author ? collect(preg_split('/\s+/u', trim($author)))->take(2)->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))->implode('') : null;
@endphp
<x-central::layout :seo="$seo" page="site-post">
    <span class="site-glow site-glow--page" aria-hidden="true"></span>
    <article class="relative mx-auto max-w-[620px] px-4 pt-6 lg:pt-[33px]" aria-labelledby="post-title">
        <x-central::breadcrumbs :items="$seo->breadcrumbs ?? []" data-reveal="fade-up" />
        <h1 id="post-title" class="mt-4 text-2xl leading-tight font-bold text-site-ink lg:text-[32px]" data-reveal="fade-up">{{ $post->title }}</h1>
        <div class="mt-4 flex items-center gap-3 text-xs text-site-gray" data-reveal="fade-up" data-reveal-delay="80">
            @if ($author)
                <span class="grid size-8 place-items-center rounded-full bg-site-tint text-[10px] font-medium text-primary" aria-hidden="true">{{ $initials }}</span>
            @endif
            <div class="flex flex-col">
                @if ($author)<span class="font-medium text-site-ink">{{ $author }}</span>@endif
                <span>
                    @if ($post->published_at)<time datetime="{{ $post->published_at->toDateString() }}">{{ $post->published_at->locale(app()->getLocale())->translatedFormat('j M Y') }}</time> · @endif{{ __('content::site.common.min_read', ['count' => $post->reading_minutes]) }}
                    @if ($post->category) · <a href="{{ route('central.blog.index', ['category' => $post->category->slug]) }}" class="site-link text-primary">{{ $post->category->name }}</a>@endif
                </span>
            </div>
        </div>
        @if ($cover['src'] ?? $cover['webp'] ?? null)
            <figure class="mt-4 overflow-hidden rounded-3xl bg-site-tint" data-reveal="zoom-in" data-reveal-delay="120">
                <picture>
                    @if (! empty($cover['avif']))<source srcset="{{ $cover['avif'] }}" type="image/avif">@endif
                    @if (! empty($cover['webp']))<source srcset="{{ $cover['webp'] }}" type="image/webp">@endif
                    <img src="{{ $cover['src'] ?? $cover['webp'] }}" alt="{{ __('content::site.blog.cover_alt', ['title' => $post->title]) }}" width="1216" height="768" fetchpriority="high" class="aspect-[589/322] w-full object-cover">
                </picture>
            </figure>
        @endif
        <div class="site-prose mt-6 text-sm" data-reveal="fade-up">{!! $post->body !!}</div>

        <div class="mt-6 flex flex-wrap items-center gap-2" data-reveal="fade-up">
            <span class="me-1 text-xs font-medium text-site-ink">{{ __('content::site.blog.share') }}</span>
            <button type="button" class="site-social text-primary" data-copy-link="{{ $post->url() }}" data-copied="{{ __('content::site.blog.copied') }}" aria-label="{{ __('content::site.blog.copy_link') }}">
                <span class="grid size-7 place-items-center rounded-full bg-primary text-white"><x-ui::icon name="link" :size="14" /></span>
            </button>
            @foreach ($shareLinks as $network => $url)
                <a href="{{ $url }}" target="_blank" rel="noopener noreferrer" class="site-social text-primary" aria-label="{{ __('content::site.blog.share_on', ['network' => __('content::site.networks.'.$network)]) }}">
                    <x-central::social-icon :network="$network" :size="28" />
                </a>
            @endforeach
        </div>
    </article>

    @if ($related->isNotEmpty())
        <section class="mx-auto mt-10 max-w-[1080px] px-4" aria-labelledby="related-title">
            <h2 id="related-title" class="text-center text-xl font-medium text-site-ink lg:text-2xl" data-reveal="fade-up">{{ __('content::site.blog.related') }}</h2>
            <div class="swiper site-rail mt-6" data-rail data-rail-until="1024">
                <ul class="swiper-wrapper lg:gap-4">
                    @foreach ($related as $item)
                        <li class="swiper-slide !h-auto !w-[260px] lg:!w-[calc((100%-2rem)/3)]" data-reveal="fade-up" data-reveal-delay="{{ $loop->index * 80 }}">
                            <x-central::blog-card :post="$item" variant="related" class="shadow-[0_8px_30px_rgba(65,37,30,0.08)]" />
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif

    <div class="mx-auto mt-10 mb-16 max-w-[1080px] px-4 lg:mb-[72px]">
        <x-central::newsletter variant="band" />
    </div>
</x-central::layout>
