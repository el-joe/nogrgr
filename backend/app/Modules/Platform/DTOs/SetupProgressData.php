<?php

namespace App\Modules\Platform\DTOs;

use App\Shared\DTOs\BaseDTO;

/**
 * Setup checklist + progress (VEN-007b / VEN-008 widget / sidebar setup badge).
 */
final class SetupProgressData extends BaseDTO
{
    /**
     * @param  list<SetupStepData>  $steps
     */
    public function __construct(
        public readonly array $steps,
        public readonly int $completed,
        public readonly int $total,
        public readonly bool $tourFinished,
    ) {}

    public function percent(): int
    {
        return $this->total === 0 ? 100 : (int) floor($this->completed * 100 / $this->total);
    }

    public function remaining(): int
    {
        return $this->total - $this->completed;
    }

    public function isComplete(): bool
    {
        return $this->completed >= $this->total;
    }

    public function next(): ?SetupStepData
    {
        foreach ($this->steps as $step) {
            if (! $step->state->isComplete() && ! $step->step->isOptional()) {
                return $step;
            }
        }

        return null;
    }
}
