<?php

namespace App\Shared\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\MessageBag;

/**
 * The JSON contract shared by every surface (docs/plan/02-architecture.md):
 *   success → {success: true, message, data?, redirect?}
 *   failure → {success: false, message, data?, redirect?}
 *   422     → {message, errors}   (identical to Laravel's ValidationException rendering)
 */
final class JsonResponder
{
    /**
     * @param  array<string, string>  $headers
     */
    public static function success(
        string $message = '',
        mixed $data = null,
        ?string $redirect = null,
        int $status = 200,
        array $headers = [],
    ): JsonResponse {
        $payload = ['success' => true, 'message' => $message];

        if ($data !== null) {
            $payload['data'] = $data;
        }

        if ($redirect !== null) {
            $payload['redirect'] = $redirect;
        }

        return new JsonResponse($payload, $status, $headers);
    }

    public static function created(string $message = '', mixed $data = null, ?string $redirect = null): JsonResponse
    {
        return self::success($message, $data, $redirect, 201);
    }

    public static function error(string $message, int $status = 400, mixed $data = null, ?string $redirect = null): JsonResponse
    {
        $payload = ['success' => false, 'message' => $message];

        if ($data !== null) {
            $payload['data'] = $data;
        }

        if ($redirect !== null) {
            $payload['redirect'] = $redirect;
        }

        return new JsonResponse($payload, $status);
    }

    /**
     * @param  array<string, list<string>|string>|MessageBag  $errors
     */
    public static function validation(array|MessageBag $errors, ?string $message = null): JsonResponse
    {
        $errors = $errors instanceof MessageBag ? $errors->toArray() : $errors;

        $normalized = [];

        foreach ($errors as $field => $messages) {
            $normalized[$field] = array_values((array) $messages);
        }

        $first = collect($normalized)->flatten()->first();

        return new JsonResponse([
            'message' => $message ?? (is_string($first) ? $first : __('The given data was invalid.')),
            'errors' => $normalized,
        ], 422);
    }
}
