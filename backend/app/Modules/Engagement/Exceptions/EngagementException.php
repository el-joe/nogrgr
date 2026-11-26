<?php

namespace App\Modules\Engagement\Exceptions;

use App\Shared\Http\JsonResponder;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * A storefront engagement rule failed (review not allowed, sign-in required, …); rendered
 * with the shared JSON contract {success: false, message, data: {reason, redirect?}}.
 * Messages are storefront static texts (decision #45).
 */
final class EngagementException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 422, public readonly ?string $reason = null, public readonly ?string $redirect = null)
    {
        parent::__construct($message);
    }

    public static function signInRequired(?string $redirect = null): self
    {
        return new self(__st('favorites.sign_in_required'), 401, 'guest', $redirect);
    }

    public static function text(string $key, string $reason, int $status = 422): self
    {
        return new self(__st($key), $status, $reason);
    }

    public function render(): JsonResponse
    {
        return JsonResponder::error($this->getMessage(), $this->status, array_filter(['reason' => $this->reason, 'redirect' => $this->redirect]), $this->redirect);
    }
}
