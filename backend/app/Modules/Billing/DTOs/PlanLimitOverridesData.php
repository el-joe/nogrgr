<?php

namespace App\Modules\Billing\DTOs;

use App\Modules\Billing\Enums\PlanLimit;
use App\Modules\Billing\Enums\PlanLimitOverrideMode;
use App\Shared\DTOs\BaseDTO;

/**
 * The full override set of one store: limit value => override (null = unlimited).
 * Limits not in the set use the plan value.
 */
final class PlanLimitOverridesData extends BaseDTO
{
    /**
     * @param  array<string, int|null>  $values
     */
    public function __construct(
        public readonly array $values,
    ) {}

    /**
     * @param  array<string, array{mode: string, value?: int|string|null}>  $limits  limit value => mode / value
     */
    public static function fromModes(array $limits): self
    {
        $values = [];

        foreach (PlanLimit::cases() as $limit) {
            $row = $limits[$limit->value] ?? null;
            $mode = PlanLimitOverrideMode::tryFrom((string) ($row['mode'] ?? '')) ?? PlanLimitOverrideMode::Plan;

            if ($mode === PlanLimitOverrideMode::Unlimited) {
                $values[$limit->value] = null;
            } elseif ($mode === PlanLimitOverrideMode::Custom) {
                $values[$limit->value] = (int) ($row['value'] ?? 0);
            }
        }

        return new self($values);
    }
}
