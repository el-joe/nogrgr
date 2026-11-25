<?php

namespace App\Modules\Commerce\DTOs\Orders;

use App\Modules\Commerce\Enums\ActorType;
use App\Modules\Commerce\Enums\CancellationReason;
use App\Shared\DTOs\BaseDTO;

/**
 * Staff cancellation of an order: reason code + note (required for "other").
 */
final class CancelOrderData extends BaseDTO
{
    public function __construct(
        public readonly CancellationReason $reason,
        public readonly ActorType $actor,
        public readonly ?int $actorId = null,
        public readonly ?string $note = null,
    ) {}

    public function historyNote(): string
    {
        return trim($this->reason->label().($this->note !== null && $this->note !== '' ? ' — '.$this->note : ''));
    }
}
