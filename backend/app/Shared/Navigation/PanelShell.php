<?php

namespace App\Shared\Navigation;

use App\Shared\Navigation\Contracts\PanelNotificationFeed;
use App\Shared\Support\Ui;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Everything the panel layout (<x-layouts.panel>) needs for its shell: the signed-in
 * user of the surface's guard, the filtered sidebar sections, the command-palette
 * entries, live counts, the surface tag and the notification bell (PanelNotificationFeed,
 * bound by the Notifications module; empty when no module binds it).
 *
 * @phpstan-import-type NavSection from NavigationBuilder
 */
final class PanelShell
{
    /**
     * @var array<string, string>
     */
    private const GUARDS = ['admin' => 'admin', 'vendor' => 'vendor', 'affiliate' => 'affiliate', 'owner' => 'owner'];

    public function __construct(private readonly NavigationBuilder $builder) {}

    /**
     * @return array{user: ?Authenticatable, role: ?string, sections: list<NavSection>, commands: list<array{label: string, group: ?string, url: string}>, counts: array<string, int>, tag: string, homeUrl: ?string, notifications: array<string, mixed>}
     */
    public function for(string $surface): array
    {
        $guard = self::GUARDS[$surface] ?? 'admin';
        $user = $this->user($guard);

        /** @var list<array<string, mixed>> $config */
        $config = (array) config('navigation.'.$surface, []);
        $builder = $surface === 'vendor' && function_exists('vendor_route')
            ? $this->builder->resolveUrlsWith(static fn (string $route): string => vendor_route(Str::after($route, 'vendor.')))
            : $this->builder;
        $sections = $builder->build($config, $user instanceof Authorizable ? $user : null);

        $role = null;

        if ($user !== null && method_exists($user, 'getRoleNames')) {
            $first = $user->getRoleNames()->first();
            $role = is_string($first) ? Ui::roleLabel($first) : null;
        }

        return [
            'user' => $user,
            'role' => $role,
            'sections' => $sections,
            'commands' => $this->builder->flatten($sections),
            'counts' => [],
            'tag' => (string) __('ui.tags.'.$surface),
            'homeUrl' => $this->url($surface, 'dashboard'),
            'notifications' => $this->feed()->for($surface, $user),
        ];
    }

    private function feed(): PanelNotificationFeed
    {
        return app()->bound(PanelNotificationFeed::class)
            ? app(PanelNotificationFeed::class)
            : new NullPanelNotificationFeed;
    }

    /**
     * The signed-in user of a panel guard (admin / vendor / affiliate models all
     * implement Authenticatable; their roles come from spatie's HasRoles).
     */
    private function user(string $guard): ?Authenticatable
    {
        $user = Auth::guard($guard)->user();

        return $user instanceof Authenticatable ? $user : null;
    }

    /**
     * URL of a named route of the surface, or null when it is not registered.
     */
    public function url(string $surface, string $name): ?string
    {
        if (! Route::has($surface.'.'.$name)) {
            return null;
        }

        return $surface === 'vendor' && function_exists('vendor_route') ? vendor_route($name) : route($surface.'.'.$name);
    }
}
