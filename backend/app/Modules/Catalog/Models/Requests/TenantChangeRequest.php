<?php

namespace App\Modules\Catalog\Models\Requests;

use App\Modules\Catalog\Database\Factories\TenantChangeRequestFactory;
use App\Modules\Catalog\Enums\TenantChangeStatus;
use App\Modules\Catalog\Enums\TenantChangeType;
use App\Modules\Identity\Models\Admin;
use App\Shared\Tenancy\Concerns\BelongsToCurrentTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Store change request (ADM-018i): the vendor asks to change its selling countries or
 * categories. `requested_ids` is the full wanted list, `current_ids` the list at submission.
 *
 * @property int $id
 * @property int $tenant_id
 * @property TenantChangeType $type
 * @property list<int> $requested_ids
 * @property list<int> $current_ids
 * @property string|null $vendor_note
 * @property string|null $submitted_by
 * @property TenantChangeStatus $status
 * @property string|null $admin_note
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property Carbon|null $created_at
 * @property-read Admin|null $reviewer
 */
class TenantChangeRequest extends Model
{
    use BelongsToCurrentTenant;
    use CentralConnection;

    /** @use HasFactory<TenantChangeRequestFactory> */
    use HasFactory;

    protected $table = 'catalog_tenant_change_requests';

    protected $guarded = ['id'];

    protected static function newFactory(): TenantChangeRequestFactory
    {
        return TenantChangeRequestFactory::new();
    }

    /**
     * Ids the request adds to the current list.
     *
     * @return list<int>
     */
    public function addedIds(): array
    {
        return array_values(array_diff(array_map('intval', $this->requested_ids), array_map('intval', $this->current_ids)));
    }

    /**
     * Ids the request removes from the current list.
     *
     * @return list<int>
     */
    public function removedIds(): array
    {
        return array_values(array_diff(array_map('intval', $this->current_ids), array_map('intval', $this->requested_ids)));
    }

    public function isPending(): bool
    {
        return $this->status === TenantChangeStatus::Pending;
    }

    /**
     * @return BelongsTo<Admin, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'reviewed_by');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TenantChangeType::class,
            'status' => TenantChangeStatus::class,
            'requested_ids' => 'array',
            'current_ids' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }
}
