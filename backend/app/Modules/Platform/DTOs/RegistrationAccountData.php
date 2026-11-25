<?php

namespace App\Modules\Platform\DTOs;

use App\Shared\DTOs\BaseDTO;

/**
 * Complete registration › account step (WEB-018).
 */
final class RegistrationAccountData extends BaseDTO
{
    public function __construct(
        public readonly string $name,
        public readonly string $password,
    ) {}
}
