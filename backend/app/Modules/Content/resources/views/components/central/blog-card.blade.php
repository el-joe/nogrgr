{{--
    Blog card (Figma Frame 61 12:41772 / blog list 127:11911): white, radius 24, 251px
    cover, title Md-24 one line, excerpt Reg-16 gray two lines, "Read More" with the
    export icon. variant: home (Figma home card) | list (category + date meta) | related.
--}}
@props(['post', 'variant' => 'home', 'eager' => false])
@php
    $cover = $post->coverSources();
    $date = $post->published_at?->locale(app()->getLocale())->translatedFormat('j M Y');
@endphp
<article {{ $attributes->class(['site-blog-card group relative flex h-full flex-col overflow-hidden rounded-3xl bg-white']) }}>
    <div @class(['relative overflow-hidden bg-site-tint', 'aspect-[397/251]' => $variant !== 'related', 'aspect-[16/9]' => $variant === 'related'])>
        @if ($cover['src'] ?? $cover['webp'] ?? null)
            <picture>
                @if (! empty($cover['avif']))<source srcset="{{ $cover['avif'] }}" type="image/avif">@endif
                @if (! empty($cover['webp']))<source srcset="{{ $cover['webp'] }}" type="image/webp">@endif
                <img src="{{ $cover['src'] ?? $cover['webp'] }}" alt="{{ __('content::site.blog.cover_alt', ['title' => $post->title]) }}" width="794" height="502"
                    class="site-zoom size-full object-cover" @if ($eager) fetchpriority="high" @else loading="lazy" @endif decoding="async">
            </picture>
        @else
            <div class="grid size-full place-items-center text-primary"><x-ui::icon name="file" :size="40" /></div>
        @endif
    </div>
    <div @class(['flex flex-1 flex-col gap-4 p-4', 'gap-2' => $variant !== 'home'])>
        @if ($variant !== 'home' && $post->category)
            <p class="text-xs font-medium text-primary">{{ $post->category->name }}</p>
        @endif
        <div class="flex flex-col gap-2">
            <h3 @class(['font-medium text-site-ink', 'truncate text-2xl' => $variant === 'home', 'line-clamp-2 text-lg' => $variant !== 'home'])>
                <a href="{{ $post->url() }}" class="site-stretched after:absolute after:inset-0 focus-visible:outline-none">{{ $post->title }}</a>
            </h3>
            @if ($variant !== 'related')
                <p @class(['line-clamp-2 text-site-gray', 'text-base' => $variant === 'home', 'text-sm' => $variant !== 'home'])>{{ $post->excerpt }}</p>
            @endif
        </div>
        @if ($variant === 'home')
            <span class="mt-auto inline-flex items-center gap-2 text-base text-primary underline underline-offset-2">
                <img src="{{ \Illuminate\Support\Facades\Vite::asset('resources/images/central/home/read-more.svg') }}" alt="" width="19" height="19" class="size-[19px] rtl:-scale-x-100">
                {{ __('content::site.common.read_more') }}
            </span>
        @elseif ($variant === 'list')
            <p class="mt-auto text-xs text-site-gray">
                @if ($date)<time datetime="{{ $post->published_at->toDateString() }}">{{ $date }}</time> · @endif{{ __('content::site.common.min_read', ['count' => $post->reading_minutes]) }}
            </p>
        @endif
    </div>
</article>
