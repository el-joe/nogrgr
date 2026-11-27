<?php

namespace App\Modules\Notifications\DTOs;

use App\Shared\DTOs\BaseDTO;
use Illuminate\Http\UploadedFile;

/**
 * Admin › Email layout (MAIL-001) input: brand colours, footer text per locale, social
 * links and the logo (upload / remove; none = the platform logo from General settings).
 */
final class MailLayoutSettingsData extends BaseDTO
{
    /**
     * @param  array<string, string>  $footer  locale => footer text
     * @param  array<string, string|null>  $socials  network => URL
     */
    public function __construct(
        public readonly string $primaryColor,
        public readonly string $backgroundColor,
        public readonly array $footer,
        public readonly array $socials,
        public readonly bool $showSocials,
        public readonly ?string $address = null,
        public readonly ?string $helpUrl = null,
        public readonly ?string $privacyUrl = null,
        public readonly ?UploadedFile $logo = null,
        public readonly bool $removeLogo = false,
    ) {}
}
