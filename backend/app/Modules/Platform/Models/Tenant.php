<?php

namespace App\Modules\Platform\Models;

use App\Modules\Platform\Database\Factories\TenantFactory;
use App\Modules\Platform\Enums\TenantStatus;
use App\Modules\Platform\Support\TenantSlug;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
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
 * @property int|null $owner_id
 * @property TenantStatus $status
 * @property string|null $owner_name
 * @property string|null $owner_email
 * @property string|null $owner_phone
 * @property int|null $default_country_id
 * @property list<int>|null $selling_country_ids
 * @property list<int>|null $selling_category_ids
 * @property int $profit_percent_bps
 * @property int $affiliate_commission_bps
 * @property int $provisioning_attempts
 * @property string|null $provisioning_error
 * @property Carbon|null $provisioned_at
 * @property Carbon|null $suspended_at
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
        return [
            'id', 'name', 'slug', 'owner_id', 'status', 'owner_name', 'owner_email', 'owner_phone', 'default_country_id',
            'selling_country_ids', 'selling_category_ids', 'profit_percent_bps', 'affiliate_commission_bps', 'provisioning_attempts',
            'provisioning_error', 'provisioned_at', 'suspended_at', 'created_at', 'updated_at',
        ];
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
            'selling_country_ids' => 'array',
            'selling_category_ids' => 'array',
            'profit_percent_bps' => 'integer',
            'affiliate_commission_bps' => 'integer',
            'provisioning_attempts' => 'integer',
            'provisioned_at' => 'datetime',
            'suspended_at' => 'datetime',
        ];
    }

    /**
     * Default store host ({slug}.{base domain}).
     */
    public function subdomainHost(): string
    {
        return TenantSlug::subdomainHost($this->slug);
    }

    /**
     * @return HasMany<DomainRequest, $this>
     */
    public function domainRequests(): HasMany
    {
        return $this->hasMany(DomainRequest::class)->withoutGlobalScopes();
    }

    /**
     * @return HasOne<ComplianceProfile, $this>
     */
    public function complianceProfile(): HasOne
    {
        return $this->hasOne(ComplianceProfile::class)->withoutGlobalScopes();
    }
}
