<?php

namespace App\Shared\Routing;

/**
 * One mounting of a surface's route files: optional domain, URI prefix, name prefix, middleware.
 */
final readonly class RouteMount
{
    /**
     * @param  list<string>  $middleware
     * @param  array<string, string>  $wheres
     */
    public function __construct(
        public Surface $surface,
        public string $prefix,
        public string $namePrefix,
        public array $middleware,
        public ?string $domain = null,
        public array $wheres = [],
    ) {}

    public function isCentralHost(): bool
    {
        return $this->domain !== null;
    }
}
