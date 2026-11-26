{{-- Previous / next pager for account lists (LengthAwarePaginator); hidden on one page. --}}
@props(['page'])
@if ($page->lastPage() > 1)
    <nav class="flex items-center justify-between gap-3" aria-label="{{ __st('orders.page_of', ['page' => $page->currentPage(), 'pages' => $page->lastPage()]) }}">
        @if ($page->onFirstPage())
            <span class="sf-btn sf-btn-outline min-h-9 px-4 text-xs opacity-50" aria-disabled="true"><x-ui::icon name="chevron-left" :size="16" class="rtl:-scale-x-100" />{{ __st('orders.previous') }}</span>
        @else
            <a href="{{ $page->previousPageUrl() }}" rel="prev" class="sf-btn sf-btn-outline min-h-9 px-4 text-xs"><x-ui::icon name="chevron-left" :size="16" class="rtl:-scale-x-100" />{{ __st('orders.previous') }}</a>
        @endif
        <span class="text-xs text-sf-muted">{{ __st('orders.page_of', ['page' => $page->currentPage(), 'pages' => $page->lastPage()]) }}</span>
        @if ($page->hasMorePages())
            <a href="{{ $page->nextPageUrl() }}" rel="next" class="sf-btn sf-btn-outline min-h-9 px-4 text-xs">{{ __st('orders.next') }}<x-ui::icon name="chevron-right" :size="16" class="rtl:-scale-x-100" /></a>
        @else
            <span class="sf-btn sf-btn-outline min-h-9 px-4 text-xs opacity-50" aria-disabled="true">{{ __st('orders.next') }}<x-ui::icon name="chevron-right" :size="16" class="rtl:-scale-x-100" /></span>
        @endif
    </nav>
@endif
