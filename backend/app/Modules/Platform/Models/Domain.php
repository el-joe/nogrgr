<?php

namespace App\Modules\Platform\Models;

use App\Modules\Platform\Enums\DomainType;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Models\Domain as BaseDomain;

/**
 * Tenancy infrastructure row (sub-domain label or custom host). Exempt from the
 * tenant scope: it is what identifies the tenant in the first place.
 *
 * @property int $id
 * @property string $domain
 * @property int $tenant_id
 * @property DomainType $type
 * @property bool $is_primary
 */
class Domain extends BaseDomain
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => DomainType::class,
            'is_primary' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
