{{--
    Pagination (Figma 33:10533) for server-paginated lists: "Showing x–y of N" + 32px page
    buttons (active = brand); mobile: Prev / Page x of N / Next.
--}}
@props(['paginator'])
@php
    $number = fn ($value) => \Illuminate\Support\Number::format($value, locale: \App\Shared\Support\Locale::numberLocale());
    $current = $paginator->currentPage();
    $last = $paginator->lastPage();
    $pages = collect([1, $last, $current - 1, $current, $current + 1])->filter(fn ($page) => $page >= 1 && $page <= $last)->unique()->sort()->values();
@endphp
<nav {{ $attributes->class(['flex flex-wrap items-center justify-between gap-3 text-[13px]']) }} aria-label="{{ __('ui.table.pagination') }}">
    <p class="text-ink-secondary">
        @if ($paginator->total() > 0)
            {{ __('ui.table.showing', ['from' => $number($paginator->firstItem()), 'to' => $number($paginator->lastItem()), 'total' => $number($paginator->total())]) }}
        @else
            {{ __('ui.table.showing_none') }}
        @endif
    </p>
    <div class="flex items-center gap-1">
        @if ($paginator->onFirstPage())
            <span class="grid size-8 place-items-center rounded-chip text-ink-muted" aria-disabled="true"><x-ui::icon name="chevron-left" :size="16" /></span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="grid size-8 place-items-center rounded-chip text-ink hover:bg-subtle" aria-label="{{ __('ui.table.previous') }}"><x-ui::icon name="chevron-left" :size="16" /></a>
        @endif
        <span class="hidden items-center gap-1 sm:flex">
            @foreach ($pages as $index => $page)
                @if ($index > 0 && $page - $pages[$index - 1] > 1)
                    <span class="px-1 text-ink-muted" aria-hidden="true">…</span>
                @endif
                <a href="{{ $paginator->url($page) }}" @class(['grid h-8 min-w-8 place-items-center rounded-chip px-2 font-medium', 'bg-primary text-on-primary' => $page === $current, 'text-ink hover:bg-subtle' => $page !== $current]) @if ($page === $current) aria-current="page" @endif>{{ $number($page) }}</a>
            @endforeach
        </span>
        <span class="px-2 text-ink-secondary sm:hidden">{{ __('ui.table.page_of', ['page' => $number($current), 'pages' => $number($last)]) }}</span>
        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="grid size-8 place-items-center rounded-chip text-ink hover:bg-subtle" aria-label="{{ __('ui.table.next') }}"><x-ui::icon name="chevron-right" :size="16" /></a>
        @else
            <span class="grid size-8 place-items-center rounded-chip text-ink-muted" aria-disabled="true"><x-ui::icon name="chevron-right" :size="16" /></span>
        @endif
    </div>
</nav>
