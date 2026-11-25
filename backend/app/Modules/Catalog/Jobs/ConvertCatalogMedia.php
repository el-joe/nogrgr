<?php

namespace App\Modules\Catalog\Jobs;

use App\Modules\Catalog\Enums\MediaConversionStatus;
use App\Modules\Catalog\Models\CatalogMedia;
use App\Modules\Catalog\Services\Media\ImageVariantConverter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Writes the webp / avif derivatives of one gallery image and records the state shown
 * on the product media tab (pending → done / failed).
 */
class ConvertCatalogMedia implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly int $mediaId) {}

    public function handle(ImageVariantConverter $converter): void
    {
        $media = CatalogMedia::query()->find($this->mediaId);

        if ($media === null) {
            return;
        }

        $variants = $converter->convert($media->disk, $media->path);

        $media->forceFill([
            'webp_path' => $variants['webp'],
            'avif_path' => $variants['avif'],
            'conversion_status' => $variants['webp'] === null && $variants['avif'] === null ? MediaConversionStatus::Skipped : MediaConversionStatus::Done,
        ])->save();
    }

    public function failed(?Throwable $exception): void
    {
        CatalogMedia::query()->whereKey($this->mediaId)->update(['conversion_status' => MediaConversionStatus::Failed->value]);
    }
}
