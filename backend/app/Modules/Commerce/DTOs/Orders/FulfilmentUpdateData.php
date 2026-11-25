<?php

namespace App\Modules\Commerce\DTOs\Orders;

use App\Modules\Commerce\Enums\ActorType;
use App\Modules\Commerce\Enums\FulfilmentStatus;
use App\Shared\DTOs\BaseDTO;

/**
 * A shipping progress step (admin shipping queue / detail, vendor own-product orders).
 */
final class FulfilmentUpdateData extends BaseDTO
{
    public function __construct(
        public readonly FulfilmentStatus $status,
        public readonly ActorType $actor,
        public readonly ?int $actorId = null,
        public readonly ?int $shippingCompanyId = null,
        public readonly ?string $trackingNumber = null,
        public readonly ?string $location = null,
        public readonly ?string $note = null,
        public readonly ?int $branchId = null,
        public readonly bool $notifyCustomer = true,
    ) {}
}
