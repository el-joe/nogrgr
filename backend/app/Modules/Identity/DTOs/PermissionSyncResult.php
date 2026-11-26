<?php

namespace App\Modules\Identity\DTOs;

use App\Shared\DTOs\BaseDTO;

/**
 * One database synced from the PermissionCatalog: permissions created and permissions
 * the system roles did not hold before.
 */
final class PermissionSyncResult extends BaseDTO
{
    public function __construct(
        public readonly int $created,
        public readonly int $granted,
    ) {}
}
