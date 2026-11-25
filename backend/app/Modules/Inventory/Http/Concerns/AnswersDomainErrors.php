<?php

namespace App\Modules\Inventory\Http\Concerns;

use App\Shared\Http\JsonResponder;
use DomainException;
use Illuminate\Http\JsonResponse;

/**
 * Run a service call and answer the panel JSON contract: success toast (+ optional
 * redirect), or a 422 with the message of a business rule (branch still holding stock…).
 */
trait AnswersDomainErrors
{
    protected function attempt(callable $action, string $message, ?string $redirect = null): JsonResponse
    {
        try {
            $action();
        } catch (DomainException $exception) {
            return JsonResponder::error($exception->getMessage(), 422);
        }

        return JsonResponder::success($message, redirect: $redirect);
    }
}
