<?php

namespace App\Modules\Platform\Legacy\Support;

use App\Modules\Platform\Legacy\Enums\LegacyIssueLevel;
use Illuminate\Support\Facades\Storage;

/**
 * Copies old public-disk files into the new public disk under a stable path
 * (`<directory>/legacy/<old relative path>`), so re-runs overwrite instead of
 * duplicating. Missing source files are reported; a dry run only checks existence.
 */
class LegacyMedia
{
    public const DISK = 'public';

    /**
     * @return array{path: string, size: int, mime: string|null, width: int|null, height: int|null}|null
     */
    public function copy(LegacyContext $context, string $entity, string|int $legacyKey, ?string $oldPath, string $directory): ?array
    {
        if ($oldPath === null || $oldPath === '') {
            return null;
        }

        if (preg_match('#^https?://#i', $oldPath) === 1) {
            $context->report->issue(LegacyIssueLevel::Warning, $context->scope(), $entity, $legacyKey, 'Remote image URL not copied');

            return null;
        }

        $source = $context->source->mediaPath($oldPath);

        if (! is_file($source)) {
            $context->report->issue(LegacyIssueLevel::Warning, $context->scope(), $entity, $legacyKey, 'Image file missing in old storage');

            return null;
        }

        $target = trim($directory, '/').'/legacy/'.ltrim($oldPath, '/');
        $size = (int) filesize($source);
        $mime = mime_content_type($source) ?: null;
        [$width, $height] = @getimagesize($source) ?: [null, null];

        if (! $context->dryRun() && $context->options->copyMedia) {
            $disk = Storage::disk(self::DISK);

            if (! $disk->exists($target) || $disk->size($target) !== $size) {
                $stream = fopen($source, 'rb');
                $disk->put($target, $stream);

                if (is_resource($stream)) {
                    fclose($stream);
                }
            }
        }

        return ['path' => $target, 'size' => $size, 'mime' => $mime, 'width' => $width, 'height' => $height];
    }
}
