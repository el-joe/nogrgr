{{-- Static text key + group ("storefront"). --}}
<span class="grid min-w-0">
    <bdi dir="ltr" class="truncate font-medium text-ink">{{ $row['key'] }}</bdi>
    <span class="text-xs text-ink-secondary">{{ \Illuminate\Support\Str::before($row['key'], '.') }}</span>
</span>
