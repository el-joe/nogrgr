{{-- Page title, its address and the System badge (system pages cannot be deleted). --}}
<div class="grid min-w-0 gap-0.5">
    <span class="flex items-center gap-2">
        <span class="truncate font-semibold text-ink">{{ $page->title ?: $page->slug }}</span>
        @if ($page->type === \App\Modules\Storefront\Enums\StorePageType::System)
            <x-ui::badge>{{ __('content::store_pages.system_badge') }}</x-ui::badge>
        @endif
    </span>
    <span class="truncate text-xs text-ink-secondary" dir="ltr">/pages/{{ $page->slug }}</span>
</div>
