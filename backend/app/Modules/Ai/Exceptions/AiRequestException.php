<?php

namespace App\Modules\Ai\Exceptions;

use App\Modules\Ai\Enums\AiErrorType;
use RuntimeException;
use Throwable;

/**
 * A provider call failed; `type` decides the status and the message shown to users.
 * The provider's raw message is kept in `detail` for logs only.
 */
final class AiRequestException extends RuntimeException
{
    public function __construct(
        public readonly AiErrorType $type,
        public readonly ?string $detail = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct((string) $type->label(), $type->httpStatus(), $previous);
    }
}
