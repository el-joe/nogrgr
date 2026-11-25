<?php

namespace App\Modules\Platform\DTOs;

use App\Shared\DTOs\BaseDTO;

final class AuditEntryData extends BaseDTO
{
    /**
     * @param  array<string, mixed>|null  $changes
     */
    public function __construct(
        public readonly string $action,
        public readonly ?string $subjectType = null,
        public readonly ?string $subjectId = null,
        public readonly ?array $changes = null,
    ) {}
}
