<?php

namespace App\Modules\Integrations\DTOs;

use App\Shared\DTOs\BaseDTO;
use Illuminate\Http\UploadedFile;

/**
 * Admin › Settings › General (ADM-101). Branding images: a new upload replaces the
 * stored file, `remove*` clears it, neither keeps it.
 */
final class GeneralSettingsData extends BaseDTO
{
    /**
     * @param  array<string, string>  $platformName  locale => name
     * @param  array<string, string|null>  $socials  network => URL
     * @param  array<string, UploadedFile>  $images  logo_light / logo_dark / favicon
     * @param  list<string>  $removeImages
     */
    public function __construct(
        public readonly array $platformName,
        public readonly ?string $supportEmail,
        public readonly ?string $supportPhone,
        public readonly ?string $supportPhoneCountry,
        public readonly string $timezone,
        public readonly string $defaultLanguage,
        public readonly int $archiveDays,
        public readonly array $socials,
        public readonly array $images = [],
        public readonly array $removeImages = [],
    ) {}
}
