<?php

namespace App\Modules\Content\Exceptions;

use App\Shared\Http\JsonResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * A content rule refused the action (system page delete, category in use…): JSON 422
 * {success: false, message} or back() with the message.
 */
class ContentRuleException extends RuntimeException
{
    public function render(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return JsonResponder::error($this->getMessage(), 422);
        }

        return back()->with('error', $this->getMessage());
    }

    public static function systemPage(): self
    {
        return new self(__('content::admin.pages.system_locked'));
    }
}
