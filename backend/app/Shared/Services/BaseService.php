<?php

namespace App\Shared\Services;

use Closure;
use Illuminate\Support\Facades\DB;

/**
 * Business rules live in services: validation beyond FormRequests, transactions,
 * domain events, calls to repositories. Controllers never talk to repositories directly.
 */
abstract class BaseService
{
    /**
     * Run a unit of work in a transaction on the given (default: current) connection.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    protected function transaction(Closure $callback, ?string $connection = null, int $attempts = 1): mixed
    {
        return DB::connection($connection)->transaction($callback, $attempts);
    }

    /**
     * Dispatch a domain event after the surrounding transaction commits.
     */
    protected function dispatchAfterCommit(object $event): void
    {
        DB::afterCommit(static fn () => event($event));
    }
}
