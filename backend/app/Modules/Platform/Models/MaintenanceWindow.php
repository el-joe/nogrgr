<?php

namespace App\Modules\Platform\Models;

use App\Modules\Identity\Models\Admin;
use App\Modules\Platform\Enums\MaintenanceScope;
use App\Modules\Platform\Enums\MaintenanceWindowStatus;
use App\Shared\Tenancy\Concerns\BelongsToCurrentTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * A planned or running maintenance window (ADM-113, central DB). tenant_id is set for
 * scope "store" only. Status is derived from the times (status()).
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property MaintenanceScope $scope
 * @property string $title
 * @property string|null $notes
 * @property array<string, string>|null $message
 * @property list<string>|null $allowed_ips
 * @property Carbon $starts_at
 * @property Carbon|null $ends_at
 * @property Carbon|null $ended_at
 * @property int|null $created_by
 * @property int|null $ended_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class MaintenanceWindow extends Model
{
    use BelongsToCurrentTenant;
    use CentralConnection;

    protected $table = 'maintenance_windows';

    /**
     * @var list<string>
     */
    protected $fillable = ['tenant_id', 'scope', 'title', 'notes', 'message', 'allowed_ips', 'starts_at', 'ends_at', 'ended_at', 'created_by', 'ended_by'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'scope' => MaintenanceScope::class,
            'message' => 'array',
            'allowed_ips' => 'array',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Admin, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    public function status(?Carbon $at = null): MaintenanceWindowStatus
    {
        $at ??= now();

        if ($this->ended_at !== null) {
            return $this->ended_at->lessThanOrEqualTo($this->starts_at) ? MaintenanceWindowStatus::Cancelled : MaintenanceWindowStatus::Completed;
        }

        if ($this->starts_at->greaterThan($at)) {
            return MaintenanceWindowStatus::Scheduled;
        }

        return $this->ends_at === null || $this->ends_at->greaterThan($at) ? MaintenanceWindowStatus::Active : MaintenanceWindowStatus::Completed;
    }

    /**
     * Message in the locale, else the other language, else null (generic text).
     */
    public function messageFor(string $locale): ?string
    {
        $messages = array_filter($this->message ?? [], static fn ($text): bool => is_string($text) && trim($text) !== '');

        return $messages[$locale] ?? $messages['en'] ?? (array_values($messages)[0] ?? null);
    }

    /**
     * Planned length in minutes (null = open-ended).
     */
    public function durationMinutes(): ?int
    {
        $end = $this->ended_at ?? $this->ends_at;

        return $end === null ? null : (int) max(0, $this->starts_at->diffInMinutes($end));
    }
}
