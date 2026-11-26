<?php

namespace App\Modules\Ai\Exceptions;

use App\Modules\Ai\DTOs\AiQuotaStatus;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * The store used its plan's monthly quota of an AI tool ("limit reached" state, 429).
 */
final class AiQuotaExceededException extends RuntimeException
{
    public function __construct(public readonly AiQuotaStatus $status)
    {
        parent::__construct((string) __('ai::tools.quota.reached', ['max' => (int) $status->max, 'date' => $status->resetsAt->translatedFormat('j M Y')]), 429);
    }

    public function render(): JsonResponse
    {
        return new JsonResponse([
            'message' => $this->getMessage(),
            'error' => 'quota_reached',
            'quota' => $this->status->toClient(),
        ], 429);
    }
}
