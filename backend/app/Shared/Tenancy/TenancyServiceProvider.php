<?php

namespace App\Shared\Tenancy;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Http\Kernel as HttpKernel;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\ParallelTesting;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\PermissionRegistrar;
use Stancl\JobPipeline\JobPipeline;
use Stancl\Tenancy\Contracts\Tenant;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\DatabaseConfig;
use Stancl\Tenancy\Events;
use Stancl\Tenancy\Jobs;
use Stancl\Tenancy\Listeners;
use Stancl\Tenancy\Middleware;

/**
 * stancl/tenancy wiring: DB-per-tenant named `nogrgr_tenant_{id}` (+ `_test_{token}`
 * in parallel test processes, + `.sqlite` on SQLite), provisioning pipeline,
 * per-tenant permission cache, 404 for unknown hosts.
 */
class TenancyServiceProvider extends ServiceProvider
{
    public const TEMPLATE_CONNECTION = 'tenant_template';

    public function register(): void
    {
        $this->registerTemplateConnection();
        $this->registerDatabaseNames();
        $this->pinSessionsToCentralConnection();
    }

    public function boot(): void
    {
        $this->bootEvents();
        $this->bootIdentificationFailures();
        $this->makeTenancyMiddlewareHighestPriority();
    }

    /**
     * @return array<class-string, list<callable|class-string|JobPipeline>>
     */
    protected function events(): array
    {
        return [
            Events\TenantCreated::class => [
                JobPipeline::make([
                    Jobs\CreateDatabase::class,
                    Jobs\MigrateDatabase::class,
                    Jobs\SeedDatabase::class,
                ])->send(fn (Events\TenantCreated $event): Tenant => $event->tenant)
                    ->shouldBeQueued((bool) config('tenancy.provisioning.queued', false)),
            ],
            Events\TenantDeleted::class => [
                JobPipeline::make([
                    Jobs\DeleteDatabase::class,
                ])->send(fn (Events\TenantDeleted $event): Tenant => $event->tenant)
                    ->shouldBeQueued(false),
            ],
            Events\TenancyInitialized::class => [
                Listeners\BootstrapTenancy::class,
            ],
            Events\TenancyEnded::class => [
                Listeners\RevertToCentralContext::class,
            ],
            Events\TenancyBootstrapped::class => [
                static function (Events\TenancyBootstrapped $event): void {
                    $registrar = app(PermissionRegistrar::class);
                    $registrar->cacheKey = config('permission.cache.key').'.tenant.'.$event->tenancy->tenant?->getTenantKey();
                    $registrar->clearPermissionsCollection();
                },
            ],
            Events\RevertedToCentralContext::class => [
                static function (): void {
                    $registrar = app(PermissionRegistrar::class);
                    $registrar->cacheKey = (string) config('permission.cache.key');
                    $registrar->clearPermissionsCollection();
                },
            ],
        ];
    }

    protected function bootEvents(): void
    {
        foreach ($this->events() as $event => $listeners) {
            foreach ($listeners as $listener) {
                if ($listener instanceof JobPipeline) {
                    $listener = $listener->toListener();
                }

                Event::listen($event, $listener);
            }
        }
    }

    /**
     * A dedicated connection (clone of central) used by the tenant DB managers for
     * CREATE/DROP DATABASE, so DDL never implicitly commits the central connection's
     * open transaction (e.g. RefreshDatabase in tests).
     */
    protected function registerTemplateConnection(): void
    {
        $central = (string) config('tenancy.database.central_connection');
        /** @var array<string, mixed> $template */
        $template = (array) config("database.connections.{$central}", []);

        if (in_array($template['driver'] ?? null, ['mysql', 'mariadb'], true)) {
            $template['database'] = 'information_schema';
        }

        config([
            'database.connections.'.self::TEMPLATE_CONNECTION => $template,
            'tenancy.database.template_tenant_connection' => self::TEMPLATE_CONNECTION,
        ]);
    }

    protected function registerDatabaseNames(): void
    {
        DatabaseConfig::generateDatabaseNamesUsing(static fn (TenantWithDatabase $tenant): string => TenantDatabaseName::for($tenant->getTenantKey()));
    }

    /**
     * Tenancy middleware runs before StartSession; keep the session table central
     * unless SESSION_CONNECTION says otherwise.
     */
    protected function pinSessionsToCentralConnection(): void
    {
        if (config('session.connection') === null) {
            config(['session.connection' => config('tenancy.database.central_connection')]);
        }
    }

    protected function bootIdentificationFailures(): void
    {
        $notFound = static fn () => abort(404);

        Middleware\InitializeTenancyByDomain::$onFail = $notFound;
        Middleware\InitializeTenancyBySubdomain::$onFail = $notFound;
        Middleware\InitializeTenancyByRequestData::$onFail = static fn () => abort(404, 'Unknown tenant.');
    }

    protected function makeTenancyMiddlewareHighestPriority(): void
    {
        $kernel = $this->app->make(Kernel::class);

        if (! $kernel instanceof HttpKernel) {
            return;
        }

        $tenancyMiddleware = [
            Middleware\PreventAccessFromCentralDomains::class,
            Middleware\InitializeTenancyByDomain::class,
            Middleware\InitializeTenancyBySubdomain::class,
            Middleware\InitializeTenancyByDomainOrSubdomain::class,
            Middleware\InitializeTenancyByPath::class,
            Middleware\InitializeTenancyByRequestData::class,
        ];

        foreach (array_reverse($tenancyMiddleware) as $middleware) {
            $kernel->prependToMiddlewarePriority($middleware);
        }
    }

    /**
     * Exposed for tests/tools: the current parallel test token, or null.
     */
    public static function parallelToken(): ?string
    {
        $token = ParallelTesting::token();

        return $token === false ? null : (string) $token;
    }
}
