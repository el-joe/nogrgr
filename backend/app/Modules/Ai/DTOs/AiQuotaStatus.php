<?php

namespace App\Modules\Ai\DTOs;

use App\Modules\Billing\Enums\PlanLimit;
use App\Shared\DTOs\BaseDTO;
use Illuminate\Support\Carbon;

/**
 * A store's monthly AI quota of one plan limit: calls used this month, the plan's
 * maximum (null = unlimited) and when the counter resets.
 */
final class AiQuotaStatus extends BaseDTO
{
    public function __construct(
        public readonly PlanLimit $limit,
        public readonly int $used,
        public readonly ?int $max,
        public readonly Carbon $resetsAt,
    ) {}

    public function unlimited(): bool
    {
        return $this->max === null;
    }

    public function remaining(): ?int
    {
        return $this->max === null ? null : max(0, $this->max - $this->used);
    }

    public function reached(): bool
    {
        return $this->max !== null && $this->used >= $this->max;
    }

    /**
     * "Uses 1 AI call · 2,400 / 5,000 left this month" line of the tool modals.
     */
    public function label(): string
    {
        return $this->unlimited()
            ? (string) __('ai::tools.quota.unlimited')
            : (string) __('ai::tools.quota.left', ['left' => number_format((int) $this->remaining()), 'max' => number_format((int) $this->max)]);
    }

    /**
     * @return array{limit: string, used: int, max: int|null, remaining: int|null, reached: bool, resets_at: string, label: string}
     */
    public function toClient(): array
    {
        return [
            'limit' => $this->limit->value,
            'used' => $this->used,
            'max' => $this->max,
            'remaining' => $this->remaining(),
            'reached' => $this->reached(),
            'resets_at' => $this->resetsAt->toDateString(),
            'label' => $this->label(),
        ];
    }
}
