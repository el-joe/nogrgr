<?php

use App\Modules\Platform\Models\Domain;
use App\Shared\Tenancy\Concerns\BelongsToCurrentTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Stancl\Tenancy\Database\Concerns\CentralConnection;
use Tests\Support\AppClasses;

/*
| Central models with a tenant_id column must use BelongsToCurrentTenant.
*/

const TENANT_SCOPE_EXEMPT = [
    Domain::class, // identifies the tenant itself
    \App\Modules\Affiliates\Models\AffiliateClick::class, // tenant_id = the referred store; read platform-wide by admin/affiliate (#93)
    \App\Modules\Affiliates\Models\AffiliateConversion::class, // tenant_id = the referred store; read platform-wide by admin/affiliate (#93)
];

it('applies the tenant scope to every central model with tenant_id', function (): void {
    $violations = [];
    $centralModels = 0;

    foreach (AppClasses::modules() as $module) {
        foreach (array_keys(AppClasses::in("Modules/{$module}/Models")) as $class) {
            if (! class_exists($class) || ! is_subclass_of($class, Model::class) || (new ReflectionClass($class))->isAbstract()) {
                continue;
            }

            if (! in_array(CentralConnection::class, class_uses_recursive($class), true)) {
                continue;
            }

            $centralModels++;

            /** @var Model $model */
            $model = new $class;

            if (in_array($class, TENANT_SCOPE_EXEMPT, true)) {
                continue;
            }

            if (Schema::connection($model->getConnectionName())->hasColumn($model->getTable(), 'tenant_id')
                && ! in_array(BelongsToCurrentTenant::class, class_uses_recursive($class), true)) {
                $violations[] = $class;
            }
        }
    }

    expect($centralModels)->toBeGreaterThan(0)
        ->and($violations)->toBe([]);
});
