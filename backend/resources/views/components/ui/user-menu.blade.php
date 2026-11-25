{{--
    User menu (Figma 32:10608): 260 wide — profile header, My profile, Security & 2FA,
    Language, Dark mode, then Sign out (danger). Links whose routes are not registered
    yet are left out (no dead links).
--}}
@props(['user' => null, 'surface' => 'admin', 'role' => null, 'compact' => false])
@php
    $name = $user?->name ?? __('ui.user.guest');
    $role ??= $user !== null && method_exists($user, 'getRoleNames') ? \App\Shared\Support\Ui::roleLabel((string) $user->getRoleNames()->first()) : null;
    $route = fn (string $name) => app(\App\Shared\Navigation\PanelShell::class)->url($surface, $name);
    $profileUrl = $route('account.edit') ?? $route('profile.edit');
    $avatar = $user !== null && isset($user->avatar_url) && is_string($user->avatar_url) ? $user->avatar_url : null;
    $securityUrl = $route('security.edit');
    $logoutUrl = $route('logout');
    $locales = \App\Shared\Support\Locale::enabled();
    $nextLocale = collect(array_keys($locales))->first(fn ($code) => $code !== app()->getLocale()) ?? app()->getLocale();
@endphp
<x-ui::dropdown align="end" :width="260" :label="__('ui.user.menu')" {{ $attributes }}>
    <x-slot:trigger>
        <button type="button" data-dropdown-trigger aria-haspopup="menu" aria-expanded="false" class="flex items-center gap-2.5 rounded-control p-1 pe-2 text-start hover:bg-subtle">
            <x-ui::avatar :name="$name" :src="$avatar" :size="36" data-user-avatar />
            @unless ($compact)
                <span class="hidden min-w-0 lg:grid">
                    <span class="truncate text-[13px] font-medium leading-[18px] text-ink">{{ $name }}</span>
                    @if ($role)
                        <span class="truncate text-xs text-ink-secondary">{{ $role }}</span>
                    @endif
                </span>
                <x-ui::icon name="chevron-down" :size="16" class="hidden text-ink-muted lg:block" />
            @endunless
        </button>
    </x-slot:trigger>

    <div class="mb-2 flex items-center gap-3 border-b border-line px-2 pb-3 pt-1">
        <x-ui::avatar :name="$name" :src="$avatar" :size="36" data-user-avatar />
        <div class="min-w-0">
            <p class="truncate text-sm font-medium text-ink">{{ $name }}</p>
            @if ($role)
                <p class="truncate text-xs text-ink-secondary">{{ $role }}</p>
            @endif
        </div>
    </div>
    @if ($profileUrl)
        <x-ui::dropdown-item icon="users" :href="$profileUrl">{{ __('ui.user.profile') }}</x-ui::dropdown-item>
    @endif
    @if ($securityUrl)
        <x-ui::dropdown-item icon="shield" :href="$securityUrl">{{ __('ui.user.security') }}</x-ui::dropdown-item>
    @endif
    @if (count($locales) > 1)
        <x-ui::dropdown-item icon="languages" :href="request()->fullUrlWithQuery(['lang' => $nextLocale])" :lang="$nextLocale">
            {{ __('ui.user.language', ['language' => $locales[app()->getLocale()]['native'] ?? app()->getLocale()]) }}
        </x-ui::dropdown-item>
    @endif
    <button type="button" role="menuitemcheckbox" tabindex="-1" data-theme-toggle aria-checked="false" class="ui-menu-item">
        <x-ui::icon name="moon" :size="16" />
        <span class="flex-1">{{ __('ui.user.dark_mode') }}</span>
        <span class="ui-switch-visual" aria-hidden="true"></span>
    </button>
    @if ($logoutUrl)
        <x-ui::dropdown-divider />
        <form method="POST" action="{{ $logoutUrl }}">
            @csrf
            <x-ui::dropdown-item type="submit" icon="log-out" tone="danger">{{ __('ui.user.sign_out') }}</x-ui::dropdown-item>
        </form>
    @endif
</x-ui::dropdown>
