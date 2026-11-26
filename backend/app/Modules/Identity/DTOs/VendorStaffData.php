<?php

namespace App\Modules\Identity\DTOs;

use App\Shared\DTOs\BaseDTO;

/**
 * Vendor › Settings › Staff invite / edit (VEN-076a, Figma 90:115176): name, e-mail,
 * optional E.164 phone and one store role (never "owner").
 */
final class VendorStaffData extends BaseDTO
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly string $role,
        public readonly ?string $phone = null,
        public readonly ?string $locale = null,
    ) {}
}
