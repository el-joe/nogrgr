<?php

namespace App\Modules\Platform\Models;

use App\Modules\Identity\Models\Admin;
use App\Modules\Platform\Enums\CacheAction;
use App\Modules\Platform\Enums\CacheRunStatus;
use App\Shared\Tenancy\Concerns\BelongsToCurrentTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * One run of a cache tool (ADM-114): who, what, which store, when it finished. The
 * latest run per action is the "last cleared at / by" line on the page.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property CacheAction $action
 * @property CacheRunStatus $status
 * @property int|null $admin_id
 * @property int $stores
 * @property string|null $error
 * @property Carbon|null $finished_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class CacheClearRun extends Model
{
    use BelongsToCurrentTenant;
    use CentralConnection;

    protected $table = 'cache_clear_runs';

    /**
     * @var list<string>
     */
    protected $fillable = ['tenant_id', 'action', 'status', 'admin_id', 'stores', 'error', 'finished_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'action' => CacheAction::class,
            'status' => CacheRunStatus::class,
            'admin_id' => 'integer',
            'stores' => 'integer',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Admin, $this>
     */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'admin_id');
    }
}
