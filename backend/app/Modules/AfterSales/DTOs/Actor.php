<?php

namespace App\Modules\AfterSales\DTOs;

use App\Modules\Commerce\Enums\ActorType;
use App\Shared\DTOs\BaseDTO;

/**
 * Who performs an after-sales action (admin, store staff, customer, system).
 */
final class Actor extends BaseDTO
{
    public function __construct(
        public readonly ActorType $type,
        public readonly ?int $id = null,
        public readonly ?string $name = null,
    ) {}

    public static function system(): self
    {
        return new self(ActorType::System);
    }
}
