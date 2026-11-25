<?php

namespace App\Modules\Identity\DTOs;

use App\Modules\Identity\Enums\AccountStatus;
use App\Shared\DTOs\BaseDTO;

/**
 * Admin create / edit (ADM-110a). `password` null on edit = keep the current one.
 */
final class AdminData extends BaseDTO
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly string $role,
        public readonly AccountStatus $status,
        public readonly ?string $password = null,
    ) {}
}
