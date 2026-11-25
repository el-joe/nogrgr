{{--
    Blog pagination (Figma 127:11911): 32px rounded squares, white with #eee border,
    active = orange fill; ‹ › arrows mirror in RTL. Input: $paginator (LengthAwarePaginator).
--}}
@php
    $current = $paginator->currentPage();
    $last = $paginator->lastPage();
    $pages = collect([1, $last, $current - 1, $current, $current + 1])->filter(fn (int $page): bool => $page >= 1 && $page <= $last)->unique()->sort()->values();
    $box = 'grid size-8 place-items-center rounded-lg border text-xs transition-colors';
@endphp
<nav aria-label="{{ __('content::site.blog.pagination') }}">
    <ul class="flex items-center gap-2">
        <li>
            @if ($paginator->onFirstPage())
                <span class="{{ $box }} border-[#eee] text-site-border" aria-hidden="true"><x-ui::icon name="chevron-left" :size="14" class="rtl:-scale-x-100" /></span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="{{ $box }} border-[#eee] bg-white text-site-ink hover:border-primary hover:text-primary" aria-label="{{ __('content::site.common.previous') }}"><x-ui::icon name="chevron-left" :size="14" class="rtl:-scale-x-100" /></a>
            @endif
        </li>
        @foreach ($pages as $index => $page)
            @if ($index > 0 && $page - $pages[$index - 1] > 1)
                <li><span class="{{ $box }} border-[#eee] text-site-gray" aria-hidden="true">…</span></li>
            @endif
            <li>
                @if ($page === $current)
                    <span class="{{ $box }} border-primary bg-primary font-medium text-white" aria-current="page">{{ $page }}</span>
                @else
                    <a href="{{ $paginator->url($page) }}" class="{{ $box }} border-[#eee] bg-white text-site-ink hover:border-primary hover:text-primary">{{ $page }}</a>
                @endif
            </li>
        @endforeach
        <li>
            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="{{ $box }} border-[#eee] bg-white text-site-ink hover:border-primary hover:text-primary" aria-label="{{ __('content::site.common.next') }}"><x-ui::icon name="chevron-right" :size="14" class="rtl:-scale-x-100" /></a>
            @else
                <span class="{{ $box }} border-[#eee] text-site-border" aria-hidden="true"><x-ui::icon name="chevron-right" :size="14" class="rtl:-scale-x-100" /></span>
            @endif
        </li>
    </ul>
</nav>
