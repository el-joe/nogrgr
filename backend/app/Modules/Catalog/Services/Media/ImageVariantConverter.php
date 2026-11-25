<?php

namespace App\Modules\Catalog\Services\Media;

use GdImage;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Writes webp and avif siblings of an uploaded catalog image (GD; same disk, same
 * directory, same base name). Formats the PHP build cannot encode are skipped (null).
 */
class ImageVariantConverter
{
    public const WEBP_QUALITY = 82;

    public const AVIF_QUALITY = 60;

    /**
     * @return array{webp: string|null, avif: string|null}
     */
    public function convert(string $disk, string $path): array
    {
        $storage = Storage::disk($disk);
        $contents = $storage->get($path);

        if ($contents === null) {
            throw new RuntimeException("Image [{$path}] is missing.");
        }

        $image = @imagecreatefromstring($contents);

        if (! $image instanceof GdImage) {
            throw new RuntimeException("Image [{$path}] cannot be decoded.");
        }

        imagepalettetotruecolor($image);
        imagealphablending($image, true);
        imagesavealpha($image, true);

        $base = preg_replace('/\.[^.\/]+$/', '', $path) ?? $path;
        $result = ['webp' => null, 'avif' => null];

        if (function_exists('imagewebp')) {
            $result['webp'] = $this->write($storage, $base.'.webp', static fn () => imagewebp($image, null, self::WEBP_QUALITY));
        }

        if (function_exists('imageavif')) {
            $result['avif'] = $this->write($storage, $base.'.avif', static fn () => imageavif($image, null, self::AVIF_QUALITY));
        }

        imagedestroy($image);

        return $result;
    }

    /**
     * @param  callable(): bool  $encode
     */
    private function write(Filesystem $storage, string $target, callable $encode): ?string
    {
        ob_start();
        $ok = $encode();
        $binary = (string) ob_get_clean();

        if (! $ok || $binary === '') {
            return null;
        }

        $storage->put($target, $binary);

        return $target;
    }
}
