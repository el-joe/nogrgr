<?php

namespace App\Modules\Storefront\DTOs\Themes;

use Illuminate\Http\UploadedFile;

/**
 * Admin theme edit (ADM-086a): name / description per locale and the preview image
 * (new upload, or remove the uploaded one to show the bundled Figma preview again).
 */
final readonly class ThemeMetaData
{
    /**
     * @param  array<string, string>  $name  locale => text
     * @param  array<string, string|null>  $description  locale => text
     */
    public function __construct(
        public array $name,
        public array $description,
        public ?UploadedFile $preview = null,
        public bool $removePreview = false,
    ) {}
}
