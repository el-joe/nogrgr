<?php

namespace App\Modules\Shipping\Http\Concerns;

use App\Shared\Http\JsonResponder;
use DomainException;
use Illuminate\Http\JsonResponse;

/**
 * Runs a service call and answers the panel JSON contract: success toast (+ redirect), or a
 * 422 with the business-rule message (country already in an active rule, duplicate day rule…).
 */
trait AnswersDomainErrors
{
    /**
     * @param  callable(): mixed  $action
     */
    protected function attempt(callable $action, string $message, ?string $redirect = null, bool $created = false): JsonResponse
    {
        try {
            $action();
        } catch (DomainException $exception) {
            return JsonResponder::error($exception->getMessage(), 422);
        }

        return $created ? JsonResponder::created($message, redirect: $redirect) : JsonResponder::success($message, redirect: $redirect);
    }
}
