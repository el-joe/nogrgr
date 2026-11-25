<?php

namespace App\Modules\Billing\Exceptions;

use App\Shared\Http\JsonResponder;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Finance rule errors (payout above the available balance, nothing to settle…) with a
 * translated message, rendered with the shared JSON contract {success: false, message,
 * data: {reason}}.
 */
final class FinanceException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 422, public readonly ?string $reason = null)
    {
        parent::__construct($message);
    }

    /**
     * @param  array<string, string|int>  $replace
     */
    public static function rule(string $key, int $status = 422, array $replace = []): self
    {
        return new self((string) __('billing::finance.errors.'.$key, $replace), $status, $key);
    }

    public function render(): JsonResponse
    {
        return JsonResponder::error($this->getMessage(), $this->status, ['reason' => $this->reason]);
    }
}
