<?php

namespace App\Modules\Storefront\Services\Themes;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use LogicException;

/**
 * Theme and template-part preview images on the platform `public` disk (central context;
 * shown with object-contain everywhere).
 */
class ThemeImageStore
{
    public const DIRECTORY = 'storefront/themes';

    public function store(UploadedFile $file, string $folder = 'previews'): string
    {
        $path = $file->store(self::DIRECTORY.'/'.$folder, 'public');

        if (! is_string($path) || $path === '') {
            throw new LogicException('The theme image could not be stored.');
        }

        return $path;
    }

    public function delete(?string $path): void
    {
        if ($path !== null && $path !== '') {
            Storage::disk('public')->delete($path);
        }
    }
}
