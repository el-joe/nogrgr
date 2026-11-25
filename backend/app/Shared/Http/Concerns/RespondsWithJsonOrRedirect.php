<?php

namespace App\Shared\Http\Concerns;

use App\Shared\Http\JsonResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Storefront forms work both as plain HTML posts (redirect + `status` flash) and as
 * fetch() calls (JSON contract {success, message, data?, redirect?}).
 */
trait RespondsWithJsonOrRedirect
{
    protected function respond(Request $request, string $message, mixed $data = null, ?string $redirect = null, int $status = 200): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return JsonResponder::success($message, $data, $redirect, $status);
        }

        return ($redirect !== null ? redirect()->to($redirect) : back())->with('status', $message);
    }
}
