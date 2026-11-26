<span class="flex min-w-0 items-center gap-3">
    <x-ui::avatar :name="$member->name" :size="36" />
    <span class="grid min-w-0">
        <span class="flex items-center gap-2 truncate font-medium text-ink">
            {{ $member->name }}
            @if ($actor && $member->is($actor))
                <x-ui::badge tone="brand">{{ __('identity::staff.you') }}</x-ui::badge>
            @endif
        </span>
        <span class="truncate text-xs text-ink-secondary"><bdi dir="ltr">{{ $member->email }}</bdi></span>
    </span>
</span>
