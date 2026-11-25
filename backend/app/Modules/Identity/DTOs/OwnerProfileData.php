<?php

namespace App\Modules\Identity\DTOs;

use App\Shared\DTOs\BaseDTO;

/**
 * Owner portal › Profile (OWN-006): personal information.
 */
final class OwnerProfileData extends BaseDTO
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $phone,
        public readonly string $locale,
    ) {}
}
