@php $sources = $post->coverSources(); @endphp
<div class="flex min-w-0 items-center gap-3">
    @if ($sources['src'])
        <img src="{{ $sources['webp'] ?? $sources['src'] }}" alt="" class="h-10 w-16 shrink-0 rounded-chip object-cover" loading="lazy">
    @else
        <span class="grid h-10 w-16 shrink-0 place-items-center rounded-chip bg-subtle text-ink-muted"><x-ui::icon name="image" :size="16" /></span>
    @endif
    <div class="min-w-0">
        <p class="truncate font-medium text-ink">{{ $post->title }}</p>
        <p class="truncate text-xs text-ink-muted" dir="ltr">/blog/{{ $post->slug }}</p>
    </div>
</div>
