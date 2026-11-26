<?php

namespace App\Modules\Support\Exceptions;

use App\Shared\Http\JsonResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * A support rule refused the action (empty message, ticket closed…). JSON 422
 * {success: false, message}, or back() with the message.
 */
class SupportRuleException extends RuntimeException
{
    public function render(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return JsonResponder::error($this->getMessage(), 422);
        }

        return back()->with('error', $this->getMessage());
    }

    public static function emptyMessage(): self
    {
        return new self(__('support::tickets.errors.empty_message'));
    }

    public static function ticketClosed(): self
    {
        return new self(__('support::tickets.errors.closed'));
    }

    public static function alreadyClosed(): self
    {
        return new self(__('support::tickets.errors.already_closed'));
    }

    public static function notClosed(): self
    {
        return new self(__('support::tickets.errors.not_closed'));
    }

    public static function attachmentFailed(): self
    {
        return new self(__('support::tickets.errors.attachment_failed'));
    }

    public static function noChanges(): self
    {
        return new self(__('support::tickets.errors.no_changes'));
    }
}
