<?php

namespace App\Modules\Platform\DTOs;

use App\Shared\DTOs\BaseDTO;

/**
 * Owner portal › add domain (OWN-004a): one of the owner's stores + the host.
 */
final class OwnerDomainData extends BaseDTO
{
    public function __construct(
        public readonly int $tenantId,
        public readonly string $host,
    ) {}
}
