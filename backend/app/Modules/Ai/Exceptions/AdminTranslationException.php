<?php

namespace App\Modules\Ai\Exceptions;

use DomainException;

/**
 * Business rule of the admin AI translation tools (answered as a 422 with the message).
 */
final class AdminTranslationException extends DomainException
{
    public static function unknownLanguage(): self
    {
        return new self((string) __('ai::admin_translation.errors.unknown_language'));
    }

    public static function nothingMissing(): self
    {
        return new self((string) __('ai::admin_translation.errors.nothing_missing'));
    }

    public static function cannotRetry(): self
    {
        return new self((string) __('ai::admin_translation.errors.cannot_retry'));
    }

    public static function notRunning(): self
    {
        return new self((string) __('ai::admin_translation.errors.not_running'));
    }

    public static function keyLocked(): self
    {
        return new self((string) __('ai::admin_translation.errors.key_locked'));
    }

    public static function noSource(): self
    {
        return new self((string) __('ai::admin_translation.errors.no_source'));
    }

    public static function placeholders(): self
    {
        return new self((string) __('ai::admin_translation.errors.placeholders'));
    }
}
