<?php

namespace App\Modules\Localization\Exceptions;

use DomainException;

/**
 * A translation rule was broken: unknown key, key locked by the platform (ADM-093),
 * duplicate key / file in the editor (ADM-092). Answered as a 422 with the message.
 */
final class TranslationRuleException extends DomainException
{
    public static function unknownKey(string $key): self
    {
        return new self(__('localization::translations.errors.unknown_key', ['key' => $key]));
    }

    public static function lockedByPlatform(string $key): self
    {
        return new self(__('localization::translations.errors.platform_locked', ['key' => $key]));
    }

    public static function keyLocked(string $key): self
    {
        return new self(__('localization::translations.errors.key_locked', ['key' => $key]));
    }

    public static function keyExists(string $key): self
    {
        return new self(__('localization::translations.errors.key_exists', ['key' => $key]));
    }

    public static function fileExists(string $file): self
    {
        return new self(__('localization::translations.errors.file_exists', ['file' => $file]));
    }

    public static function unknownFile(): self
    {
        return new self(__('localization::translations.errors.unknown_file'));
    }

    public static function languageNotPurchasable(): self
    {
        return new self(__('localization::vendor_languages.errors.not_purchasable'));
    }

    public static function defaultMustBeEnabled(): self
    {
        return new self(__('localization::vendor_languages.errors.default_disabled'));
    }

    public static function runNotRetryable(): self
    {
        return new self(__('localization::vendor_languages.errors.not_retryable'));
    }

    public static function translationUnavailable(): self
    {
        return new self(__('localization::vendor_translations.errors.ai_unavailable'));
    }

    public static function unknownContent(): self
    {
        return new self(__('localization::vendor_translations.errors.unknown_content'));
    }
}
