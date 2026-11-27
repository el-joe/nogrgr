{{-- ADM-089 row: theme name + version + uploader. --}}
<div class="grid gap-0.5">
    <a href="{{ route('admin.storefront.theme-reviews.show', $version->id) }}" class="font-medium text-ink hover:text-primary hover:underline">{{ $version->name }} {{ $version->version }}</a>
    <span class="text-xs text-ink-secondary">{{ $version->file_count }} · {{ \Illuminate\Support\Number::fileSize($version->size_bytes) }}</span>
</div>
