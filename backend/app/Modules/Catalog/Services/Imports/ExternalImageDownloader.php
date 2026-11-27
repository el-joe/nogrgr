<?php

namespace App\Modules\Catalog\Services\Imports;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

/**
 * Downloads one image of an external catalog (product gallery, variant thumbnail,
 * category image) into a temporary UploadedFile for the normal media pipeline; null
 * when it cannot be read, is not an image or is too large. Callers delete the file.
 */
class ExternalImageDownloader
{
    public const TIMEOUT_SECONDS = 30;

    public const MAX_BYTES = 10_485_760;

    public function download(string $url, string $name): ?UploadedFile
    {
        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)->retry(1, 500, throw: false)->get($url);
        } catch (ConnectionException) {
            return null;
        }

        $mime = strtolower(trim(explode(';', (string) $response->header('Content-Type'))[0]));
        $body = $response->body();

        if (! $response->successful() || ! str_starts_with($mime, 'image/') || $body === '' || strlen($body) > self::MAX_BYTES) {
            return null;
        }

        $path = tempnam(sys_get_temp_dir(), 'catalog-import-');

        if ($path === false || file_put_contents($path, $body) === false) {
            return null;
        }

        $extension = match ($mime) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'image/avif' => 'avif',
            default => 'jpg',
        };

        return new UploadedFile($path, $name.'.'.$extension, $mime, null, true);
    }
}
