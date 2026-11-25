<?php

namespace App\Modules\Identity\DTOs;

use App\Shared\DTOs\BaseDTO;

/**
 * Role create / edit (ADM-111a): name + the ticked permission names of the matrix.
 */
final class RoleData extends BaseDTO
{
    /**
     * @param  list<string>  $permissions
     */
    public function __construct(
        public readonly string $name,
        public readonly array $permissions = [],
    ) {}
}
