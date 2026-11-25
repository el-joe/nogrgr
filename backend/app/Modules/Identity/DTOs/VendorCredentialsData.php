<?php

namespace App\Modules\Identity\DTOs;

use App\Shared\DTOs\BaseDTO;

final class VendorCredentialsData extends BaseDTO
{
    public function __construct(
        public readonly string $email,
        public readonly string $password,
        public readonly bool $remember = false,
        public readonly ?string $store = null,
    ) {}
}
