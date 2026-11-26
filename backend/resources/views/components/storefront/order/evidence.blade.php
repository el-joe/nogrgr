{{--
    Return evidence pickers (photos ≤ 5 × 5 MB jpg/png/webp, one video ≤ 50 MB mp4/mov/webm —
    AfterSales ReturnUploadLimits). compact: outline "Photos" / "Video" (reply, 102:105046);
    otherwise dashed "Add photos" / "Add video" (request return, 102:105455). Chosen files are
    listed with remove buttons (storefront/core/account/returns.js).
--}}
@props(['compact' => false])
@php
    $limits = \App\Modules\AfterSales\Support\ReturnUploadLimits::toArray();
    $button = $compact
        ? 'sf-btn sf-btn-outline min-h-9 cursor-pointer px-3.5 text-xs'
        : 'sf-btn min-h-8 cursor-pointer border-dashed border-sf-accent bg-sf-surface px-3.5 text-xs text-sf-accent hover:bg-sf-accent-subtle';
@endphp
<div class="grid gap-2" data-evidence data-photo-max="{{ $limits['photos']['max_files'] }}" data-photo-kb="{{ $limits['photos']['max_size_kb'] }}" data-video-kb="{{ $limits['video']['max_size_kb'] }}"
     data-text-large="{{ __st('returns.file_too_large', ['name' => ':name', 'size' => ':size']) }}" data-text-many="{{ __st('returns.too_many_photos', ['count' => $limits['photos']['max_files']]) }}" data-text-remove="{{ __st('returns.remove_file', ['name' => ':name']) }}">
    <div class="flex flex-wrap items-center gap-2">
        <label class="{{ $button }} has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-sf-ink">
            <input type="file" name="photos[]" accept="{{ collect($limits['photos']['extensions'])->map(fn ($ext) => '.'.$ext)->implode(',') }}" multiple class="sr-only" data-evidence-photos>
            <x-ui::icon name="image" :size="16" />{{ $compact ? __st('returns.photos') : __st('returns.add_photos') }}
        </label>
        <label class="{{ $button }} has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-sf-ink">
            <input type="file" name="video" accept="{{ collect($limits['video']['extensions'])->map(fn ($ext) => '.'.$ext)->implode(',') }}" class="sr-only" data-evidence-video>
            <x-ui::icon name="camera" :size="16" />{{ $compact ? __st('returns.video') : __st('returns.add_video') }}
        </label>
        @if ($compact)<span class="text-xs text-sf-muted">{{ __st('returns.limits') }}</span>@endif
    </div>
    <ul class="flex flex-wrap gap-2 empty:hidden" data-evidence-list></ul>
    <p class="sf-error" data-error-for="photos" hidden></p>
    <p class="sf-error" data-error-for="video" hidden></p>
    <p class="sf-error" data-evidence-error hidden></p>
</div>
