<?php

namespace App\Shared\Routing;

/**
 * Collects module route files per surface. Modules register here while booting;
 * SurfaceRouter mounts them once all providers are booted (route-cache safe).
 */
class ModuleRouteRegistry
{
    /**
     * @var array<string, list<string>>
     */
    private array $files = [];

    public function add(Surface $surface, string $file): void
    {
        $this->files[$surface->value] ??= [];

        if (! in_array($file, $this->files[$surface->value], true)) {
            $this->files[$surface->value][] = $file;
        }
    }

    /**
     * @return list<string>
     */
    public function filesFor(Surface $surface): array
    {
        return $this->files[$surface->value] ?? [];
    }
}
