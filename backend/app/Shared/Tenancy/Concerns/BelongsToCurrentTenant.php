<?php

namespace App\Shared\Tenancy\Concerns;

use App\Shared\Tenancy\Scopes\CurrentTenantScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mandatory for every *central* model that has a `tenant_id` column
 * (enforced by tests/Feature/Architecture/CentralTenantScopeTest.php).
 *
 *  - adds CurrentTenantScope (filters by the current tenant in tenant context)
 *  - fills tenant_id on create in tenant context
 *  - `forTenant()` / `withoutTenantScope()` helpers for central code
 *
 * @phpstan-require-extends Model
 */
trait BelongsToCurrentTenant
{
    public static function bootBelongsToCurrentTenant(): void
    {
        static::addGlobalScope(new CurrentTenantScope);

        static::creating(static function (Model $model): void {
            if (tenancy()->initialized && $model->getAttribute('tenant_id') === null) {
                $model->setAttribute('tenant_id', tenant()?->getTenantKey());
            }
        });
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function tenant(): BelongsTo
    {
        /** @var class-string<Model> $tenantModel */
        $tenantModel = config('tenancy.tenant_model');

        return $this->belongsTo($tenantModel, 'tenant_id');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForTenant(Builder $query, int|string $tenantId): Builder
    {
        return $query->withoutGlobalScope(CurrentTenantScope::class)
            ->where($this->qualifyColumn('tenant_id'), $tenantId);
    }

    /**
     * @return Builder<static>
     */
    public static function withoutTenantScope(): Builder
    {
        return static::query()->withoutGlobalScope(CurrentTenantScope::class);
    }
}
