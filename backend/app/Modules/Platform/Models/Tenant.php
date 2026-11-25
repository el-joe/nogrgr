<?php

namespace App\Modules\Platform\Models;

use App\Modules\Platform\Database\Factories\TenantFactory;
use App\Modules\Platform\Enums\TenantStatus;
use App\Modules\Platform\Support\TenantSlug;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

/**
 * A vendor store. Its data lives in its own database `nogrgr_tenant_{id}`.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property TenantStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase;
    use HasDomains;

    /** @use HasFactory<TenantFactory> */
    use HasFactory;

    public $incrementing = true;

    protected $keyType = 'int';

    /**
     * Real columns; everything else goes to the `data` JSON column.
     *
     * @return list<string>
     */
    public static function getCustomColumns(): array
    {
        return ['id', 'name', 'slug', 'status', 'created_at', 'updated_at'];
    }

    protected static function booted(): void
    {
        static::saving(static function (Tenant $tenant): void {
            TenantSlug::assertValid((string) $tenant->slug);
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TenantStatus::class,
        ];
    }
}
