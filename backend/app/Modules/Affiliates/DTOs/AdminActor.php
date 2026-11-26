<?php

namespace App\Modules\Affiliates\DTOs;

use App\Shared\DTOs\BaseDTO;

/**
 * The admin who acted (kept on payouts as a snapshot).
 */
final class AdminActor extends BaseDTO
{
    public function __construct(
        public readonly ?int $id,
        public readonly ?string $name,
    ) {}
}
