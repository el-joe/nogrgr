<?php

namespace App\Modules\Storefront\DTOs\Themes;

use App\Modules\Storefront\Enums\ThemePartType;
use Illuminate\Http\UploadedFile;

/**
 * Admin template part create / edit (ADM-088a): theme, type, name, the settings the type
 * declares (ThemePartType::fields), flags and an optional preview image.
 */
final readonly class ThemePartData
{
    /**
     * @param  array<string, mixed>  $settings
     */
    public function __construct(
        public int $themeId,
        public ThemePartType $type,
        public string $name,
        public array $settings,
        public bool $isActive,
        public bool $isDefault,
        public ?UploadedFile $preview = null,
        public bool $removePreview = false,
    ) {}
}
