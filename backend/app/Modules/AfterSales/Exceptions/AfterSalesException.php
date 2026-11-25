<?php

namespace App\Modules\AfterSales\Exceptions;

use App\Shared\Http\JsonResponder;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Domain errors of returns and refunds with a translated message, rendered with the
 * shared JSON contract {success: false, message, data: {reason}}.
 */
final class AfterSalesException extends RuntimeException
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
        return new self((string) __('after-sales::messages.errors.'.$key, $replace), $status, $key);
    }

    public function render(): JsonResponse
    {
        return JsonResponder::error($this->getMessage(), $this->status, ['reason' => $this->reason]);
    }
}
