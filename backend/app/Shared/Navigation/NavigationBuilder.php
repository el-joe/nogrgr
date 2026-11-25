<?php

namespace App\Shared\Navigation;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Turns a panel navigation config (config/navigation/<surface>.php) into what the
 * sidebar renders:
 *  - items whose route is not registered are dropped (no dead links while modules
 *    are still being built), as are items whose permission the user lacks;
 *  - groups without visible children and sections without items disappear;
 *  - active / open flags come from the current route name.
 *
 * @phpstan-type NavItem array{key: string, label: string, icon: ?string, url: ?string, route: ?string, badge: ?string, active: bool, open: bool, children: list<array<string, mixed>>}
 * @phpstan-type NavSection array{key: string, label: ?string, items: list<NavItem>}
 */
final class NavigationBuilder
{
    /**
     * @var (callable(string): string)|null
     */
    private $urlResolver = null;

    public function __construct(private readonly Request $request) {}

    /**
     * Resolve item URLs with a custom generator (the vendor panel serves the same
     * route names on the store host and the central host).
     *
     * @param  callable(string): string  $resolver
     */
    public function resolveUrlsWith(callable $resolver): self
    {
        $clone = clone $this;
        $clone->urlResolver = $resolver;

        return $clone;
    }

    /**
     * @param  list<array<string, mixed>>  $sections
     * @return list<NavSection>
     */
    public function build(array $sections, ?Authorizable $user): array
    {
        $built = [];

        foreach ($sections as $section) {
            $items = [];

            foreach ((array) ($section['items'] ?? []) as $item) {
                $resolved = $this->item((array) $item, $user);

                if ($resolved !== null) {
                    $items[] = $resolved;
                }
            }

            if ($items !== []) {
                $built[] = [
                    'key' => (string) ($section['key'] ?? Str::random(6)),
                    'label' => isset($section['label']) ? (string) __((string) $section['label']) : null,
                    'items' => $items,
                ];
            }
        }

        return $built;
    }

    /**
     * Flat list of reachable pages for the command palette.
     *
     * @param  list<NavSection>  $sections
     * @return list<array{label: string, group: ?string, url: string}>
     */
    public function flatten(array $sections): array
    {
        $flat = [];

        foreach ($sections as $section) {
            foreach ($section['items'] as $item) {
                if ($item['url'] !== null && $item['children'] === []) {
                    $flat[] = ['label' => $item['label'], 'group' => $section['label'], 'url' => $item['url']];
                }

                foreach ($item['children'] as $child) {
                    $flat[] = ['label' => (string) $child['label'], 'group' => $item['label'], 'url' => (string) $child['url']];
                }
            }
        }

        return $flat;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return NavItem|null
     */
    private function item(array $item, ?Authorizable $user): ?array
    {
        $children = [];

        foreach ((array) ($item['children'] ?? []) as $child) {
            $resolved = $this->item((array) $child, $user);

            if ($resolved !== null) {
                $children[] = $resolved;
            }
        }

        $hasChildren = isset($item['children']);

        if ($hasChildren && $children === []) {
            return null;
        }

        if (! $hasChildren && ! $this->reachable($item, $user)) {
            return null;
        }

        $route = isset($item['route']) ? (string) $item['route'] : null;
        $active = $hasChildren
            ? in_array(true, array_column($children, 'active'), true)
            : $this->isActive($route, isset($item['active']) ? (is_array($item['active']) ? array_map('strval', $item['active']) : (string) $item['active']) : null);

        return [
            'key' => (string) ($item['key'] ?? $route ?? Str::random(6)),
            'label' => (string) __((string) ($item['label'] ?? '')),
            'icon' => isset($item['icon']) ? (string) $item['icon'] : null,
            'url' => $hasChildren ? null : $this->url((string) $route),
            'route' => $route,
            'badge' => isset($item['badge']) ? (string) $item['badge'] : null,
            'active' => $active,
            'open' => $hasChildren && $active,
            'children' => $children,
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function reachable(array $item, ?Authorizable $user): bool
    {
        $route = $item['route'] ?? null;

        if (! is_string($route) || ! Route::has($route)) {
            return false;
        }

        $permission = $item['permission'] ?? null;

        if ($permission === null) {
            return true;
        }

        return $user !== null && $user->can((string) $permission);
    }

    private function url(string $route): string
    {
        return $this->urlResolver !== null ? ($this->urlResolver)($route) : route($route);
    }

    /**
     * @param  string|list<string>|null  $pattern  one route pattern or several (any matches)
     */
    private function isActive(?string $route, string|array|null $pattern): bool
    {
        if ($route === null) {
            return false;
        }

        $pattern ??= Str::endsWith($route, '.index') ? Str::beforeLast($route, '.index').'.*' : $route;

        return $this->request->routeIs(...(array) $pattern);
    }
}
