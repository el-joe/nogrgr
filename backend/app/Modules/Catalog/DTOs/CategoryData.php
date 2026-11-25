<?php

namespace App\Modules\Catalog\DTOs;

use App\Modules\Catalog\Enums\RecordStatus;
use App\Shared\DTOs\BaseDTO;
use Illuminate\Http\UploadedFile;

/**
 * Admin create / edit of a central category. Translatable fields are locale => text.
 * `slug` null = generated from the English name. `thumbnail` is a new upload;
 * `keepThumbnail` false with no upload removes the current one.
 */
final class CategoryData extends BaseDTO
{
    /**
     * @param  array<string, string>  $name
     * @param  array<string, string>  $description
     * @param  array<string, string>  $metaTitle
     * @param  array<string, string>  $metaDescription
     */
    public function __construct(
        public readonly array $name,
        public readonly ?string $slug,
        public readonly ?int $parentId,
        public readonly RecordStatus $status,
        public readonly bool $isFeatured,
        public readonly bool $showInMenu,
        public readonly ?int $sortOrder = null,
        public readonly array $description = [],
        public readonly array $metaTitle = [],
        public readonly array $metaDescription = [],
        public readonly ?UploadedFile $thumbnail = null,
        public readonly bool $keepThumbnail = true,
    ) {}
}
