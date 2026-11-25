<?php

namespace App\Shared\Tenancy\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Restricts central rows with a tenant_id to the current tenant while tenancy
 * is initialized. In central context (admin panel, jobs) it is a no-op.
 *
 * @template TModel of Model
 *
 * @implements Scope<TModel>
 */
class CurrentTenantScope implements Scope
{
    /**
     * @param  Builder<covariant TModel>  $builder
     * @param  TModel  $model
     */
    public function apply(Builder $builder, Model $model): void
    {
        if (! tenancy()->initialized) {
            return;
        }

        $builder->where($model->qualifyColumn('tenant_id'), tenant()?->getTenantKey());
    }
}
