<div class="min-w-0">
    <p class="flex items-center gap-2 font-medium text-ink">
        <span class="truncate">{{ $page->title }}</span>
        @if ($page->is_system)
            <x-ui::badge tone="brand">{{ __('content::admin.pages.system') }}</x-ui::badge>
        @endif
        @if ($page->is_compliance)
            <x-ui::icon name="shield" :size="14" class="shrink-0 text-ink-muted" :label="__('content::admin.pages.fields.compliance')" />
        @endif
    </p>
    <p class="truncate text-xs text-ink-muted" dir="ltr">/{{ in_array($page->slug, \App\Modules\Content\Models\Page::SYSTEM_SLUGS, true) ? $page->slug : 'pages/'.$page->slug }}</p>
</div>
