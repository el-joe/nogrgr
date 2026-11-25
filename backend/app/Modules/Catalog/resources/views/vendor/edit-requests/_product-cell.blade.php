@php($image = $request->product?->media->first()?->url())
<div class="flex items-center gap-3">
    @if ($image)
        <img src="{{ $image }}" alt="" class="size-9 shrink-0 rounded-chip object-cover" loading="lazy">
    @else
        <span class="grid size-9 shrink-0 place-items-center rounded-chip bg-primary-subtle text-ink-brand"><x-ui::icon name="box" :size="16" /></span>
    @endif
    <div class="grid min-w-0">
        <span class="truncate font-medium text-ink">{{ $request->product?->name ?? $request->getAttribute('product_name') }}</span>
        <span class="text-xs text-ink-secondary" dir="ltr">{{ $request->product?->sku }}</span>
    </div>
</div>
