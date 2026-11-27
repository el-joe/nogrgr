<?php

namespace App\Modules\Catalog\Jobs;

use App\Modules\Catalog\Enums\MediaConversionStatus;
use App\Modules\Catalog\Models\CatalogMedia;
use App\Modules\Catalog\Services\Media\VideoPosterGenerator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Poster frame of one gallery video (#114). Dispatched in the owner's context: central
 * products from the central app, own products inside tenancy (the queue bootstrapper
 * restores the tenant DB + disk). Without ffmpeg the state is "skipped" (no poster).
 */
class GenerateVideoPoster implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 180;

    public function __construct(public readonly int $mediaId) {}

    public function handle(VideoPosterGenerator $generator): void
    {
        $media = CatalogMedia::query()->find($this->mediaId);

        if ($media === null || ! $media->isVideo()) {
            return;
        }

        $poster = $generator->generate($media->disk, $media->path);

        $media->forceFill([
            'poster_path' => $poster ?? $media->poster_path,
            'conversion_status' => $poster !== null ? MediaConversionStatus::Done : MediaConversionStatus::Skipped,
        ])->save();
    }

    public function failed(?Throwable $exception): void
    {
        CatalogMedia::query()->whereKey($this->mediaId)->update(['conversion_status' => MediaConversionStatus::Failed->value]);
    }
}
