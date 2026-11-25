<?php

namespace App\Modules\Shipping\DTOs;

use App\Shared\DTOs\BaseDTO;
use Illuminate\Http\UploadedFile;

/**
 * Admin create / edit of a shipping company. $logo = new upload; $keepLogo = keep the stored one.
 */
final class ShippingCompanyData extends BaseDTO
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $contactEmail,
        public readonly ?string $contactPhone,
        public readonly ?string $website,
        public readonly ?string $trackingUrlPattern,
        public readonly bool $isActive,
        public readonly ?UploadedFile $logo = null,
        public readonly bool $keepLogo = true,
    ) {}
}
