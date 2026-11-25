<?php

namespace App\Modules\Platform\DTOs;

use App\Modules\Platform\Enums\SetupStep;
use App\Modules\Platform\Enums\SetupStepState;
use App\Shared\DTOs\BaseDTO;

/**
 * One row of the setup checklist. `url` is set only when the page exists; otherwise
 * `availableAfter` names the panel area the step becomes available with.
 */
final class SetupStepData extends BaseDTO
{
    public function __construct(
        public readonly SetupStep $step,
        public readonly SetupStepState $state,
        public readonly string $title,
        public readonly string $detail,
        public readonly ?string $url = null,
        public readonly ?string $availableAfter = null,
    ) {}
}
