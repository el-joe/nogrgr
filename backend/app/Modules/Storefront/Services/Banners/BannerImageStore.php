<?php

namespace App\Modules\Storefront\Services\Banners;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use LogicException;

/**
 * Banner image files on the `public` disk: central context = the platform disk,
 * tenant context = the store's tenant-suffixed disk (served from /tenancy/assets/).
 * Records the pixel size so the storefront can reserve the space (no layout shift).
 */
class BannerImageStore
{
    public const DIRECTORY = 'storefront/banners';

    /**
     * @return array{path: string, width: int|null, height: int|null}
     */
    public function store(UploadedFile $file): array
    {
        $size = @getimagesize($file->getRealPath() ?: '');
        $path = $file->store(self::DIRECTORY, 'public');

        if (! is_string($path)) {
            throw new LogicException('The banner image could not be stored.');
        }

        return [
            'path' => $path,
            'width' => is_array($size) ? (int) $size[0] : null,
            'height' => is_array($size) ? (int) $size[1] : null,
        ];
    }

    /**
     * Copies a local file (demo seeder) to the disk.
     *
     * @return array{path: string, width: int|null, height: int|null}
     */
    public function storeLocal(string $source): array
    {
        $size = @getimagesize($source);
        $path = self::DIRECTORY.'/'.basename($source);
        Storage::disk('public')->put($path, (string) file_get_contents($source));

        return [
            'path' => $path,
            'width' => is_array($size) ? (int) $size[0] : null,
            'height' => is_array($size) ? (int) $size[1] : null,
        ];
    }

    public function delete(?string ...$paths): void
    {
        $paths = array_values(array_filter($paths, static fn (?string $path): bool => $path !== null && $path !== ''));

        if ($paths !== []) {
            Storage::disk('public')->delete($paths);
        }
    }
}
