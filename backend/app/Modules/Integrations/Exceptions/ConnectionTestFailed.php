<?php

namespace App\Modules\Integrations\Exceptions;

use App\Modules\Integrations\Enums\ConnectionErrorType;
use RuntimeException;

/**
 * Raised inside a connection test; ConnectionTestResult turns it into a typed,
 * translated failure. `detail` is the provider's own message (never a secret).
 */
final class ConnectionTestFailed extends RuntimeException
{
    public function __construct(public readonly ConnectionErrorType $type, public readonly ?string $detail = null)
    {
        parent::__construct($detail ?? $type->value);
    }
}
