<?php

namespace App\Modules\Catalog\Imports\Exceptions;

use RuntimeException;

/**
 * The external catalog could not be read (not configured, unreachable, bad response).
 */
class ExternalCatalogException extends RuntimeException
{
    public static function notConfigured(): self
    {
        return new self(__('catalog::imports.errors.not_configured'));
    }

    public static function http(int $status, int $page): self
    {
        return new self(__('catalog::imports.errors.http', ['status' => $status, 'page' => $page]));
    }

    public static function unreachable(string $detail): self
    {
        return new self(__('catalog::imports.errors.unreachable', ['detail' => mb_substr($detail, 0, 200)]));
    }

    public static function invalidResponse(int $page): self
    {
        return new self(__('catalog::imports.errors.invalid_response', ['page' => $page]));
    }
}
