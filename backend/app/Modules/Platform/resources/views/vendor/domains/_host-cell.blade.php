{{-- Domain cell: host + "primary" / "sub-domain" / "added 1 h ago". --}}
<span class="grid min-w-0">
    @if ($row['request'])
        <a href="{{ vendor_route('settings.domains.show', ['domain' => $row['request']->id]) }}" class="truncate font-medium text-ink hover:text-ink-brand"><bdi dir="ltr">{{ $row['host'] }}</bdi></a>
    @else
        <bdi dir="ltr" class="truncate font-medium text-ink">{{ $row['host'] }}</bdi>
    @endif
    <span class="text-xs text-ink-secondary">
        @if ($row['is_primary'])
            {{ __('platform::vendor_domains.primary') }}
        @elseif ($row['kind'] === 'subdomain')
            {{ __('platform::vendor_domains.subdomain') }}
        @else
            {{ __('platform::vendor_domains.added_ago', ['time' => $row['created_at']?->diffForHumans()]) }}
        @endif
    </span>
</span>
