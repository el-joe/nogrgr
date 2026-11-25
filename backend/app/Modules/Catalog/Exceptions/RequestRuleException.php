<?php

namespace App\Modules\Catalog\Exceptions;

use App\Shared\Http\JsonResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * A requests-center rule refused the action (already reviewed, payment not cancellable…).
 * JSON {success: false, message} with 422, or back() with the message.
 */
class RequestRuleException extends RuntimeException
{
    public function render(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return JsonResponder::error($this->getMessage(), 422);
        }

        return back()->with('error', $this->getMessage());
    }

    public static function alreadyReviewed(): self
    {
        return new self(__('catalog::requests.errors.already_reviewed'));
    }

    public static function pendingExists(): self
    {
        return new self(__('catalog::requests.errors.pending_exists'));
    }

    public static function noChanges(): self
    {
        return new self(__('catalog::requests.errors.no_changes'));
    }

    public static function paymentNotCancellable(): self
    {
        return new self(__('catalog::requests.errors.payment_not_cancellable'));
    }

    public static function paymentsNotSupported(): self
    {
        return new self(__('catalog::requests.errors.payments_not_supported'));
    }

    public static function emptyMessage(): self
    {
        return new self(__('catalog::requests.errors.empty_message'));
    }

    public static function attachmentMissing(): self
    {
        return new self(__('catalog::requests.errors.attachment_missing'));
    }
}
