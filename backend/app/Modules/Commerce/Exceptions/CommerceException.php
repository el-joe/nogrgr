<?php

namespace App\Modules\Commerce\Exceptions;

use App\Modules\Commerce\Enums\CartLineIssue;
use App\Modules\Commerce\Enums\CheckoutBlock;
use App\Shared\Http\JsonResponder;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Domain errors of cart / checkout / orders / payments with a translated message,
 * rendered with the shared JSON contract {success: false, message, data?}.
 */
final class CommerceException extends RuntimeException
{
    /**
     * @param  array<string, mixed>|null  $data
     */
    public function __construct(string $message, public readonly int $status = 422, public readonly ?string $reason = null, public readonly ?array $data = null)
    {
        parent::__construct($message);
    }

    public static function lineIssue(CartLineIssue $issue): self
    {
        return new self($issue->message(), 422, $issue->value);
    }

    /**
     * @param  array<string, mixed>|null  $data
     */
    public static function blocked(CheckoutBlock $block, ?array $data = null): self
    {
        return new self($block->message(), $block === CheckoutBlock::SignInRequired ? 401 : 422, $block->value, $data);
    }

    public static function notFound(string $key): self
    {
        return new self((string) __('commerce::cart.errors.'.$key), 404, $key);
    }

    public static function rule(string $key, int $status = 422): self
    {
        return new self((string) __('commerce::orders.errors.'.$key), $status, $key);
    }

    public static function message(string $message, string $reason, int $status = 422): self
    {
        return new self($message, $status, $reason);
    }

    public function render(): JsonResponse
    {
        return JsonResponder::error($this->getMessage(), $this->status, ['reason' => $this->reason] + ($this->data ?? []));
    }
}
