<?php

namespace App\Modules\Platform\DTOs;

use App\Modules\Platform\Enums\ComplianceStatus;
use App\Shared\DTOs\BaseDTO;

/**
 * Admin compliance decision: verified, needs_action (note required) or rejected (note required).
 */
final class ComplianceDecisionData extends BaseDTO
{
    public function __construct(
        public readonly ComplianceStatus $status,
        public readonly ?string $note = null,
        public readonly ?int $adminId = null,
    ) {}
}
