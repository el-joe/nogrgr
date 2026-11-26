<?php

namespace App\Modules\Identity\DTOs;

use App\Shared\DTOs\BaseDTO;

/**
 * Vendor › Settings › Account (VEN-070) profile card of the signed-in staff member.
 * `email` is null for the store owner (managed in the owner portal).
 */
final class VendorAccountData extends BaseDTO
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $email,
        public readonly string $phone,
        public readonly string $locale,
    ) {}
}
