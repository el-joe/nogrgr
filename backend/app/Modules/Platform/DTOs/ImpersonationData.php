<?php

namespace App\Modules\Platform\DTOs;

use App\Modules\Platform\Enums\ImpersonationReason;
use App\Shared\DTOs\BaseDTO;

final class ImpersonationData extends BaseDTO
{
    public function __construct(
        public readonly ImpersonationReason $reason,
        public readonly ?string $details = null,
    ) {}
}
