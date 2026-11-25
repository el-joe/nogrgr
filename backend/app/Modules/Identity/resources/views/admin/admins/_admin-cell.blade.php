<span class="flex min-w-0 items-center gap-3">
    <x-ui::avatar :name="$admin->name" :src="$admin->avatar_url" :size="36" />
    <span class="grid min-w-0">
        <span class="flex items-center gap-2 truncate font-medium text-ink">
            {{ $admin->name }}
            @if ($actor && $admin->is($actor))
                <x-ui::badge tone="brand">{{ __('identity::admins.you') }}</x-ui::badge>
            @endif
        </span>
        <span class="truncate text-xs text-ink-secondary"><bdi dir="ltr">{{ $admin->email }}</bdi></span>
    </span>
</span>
