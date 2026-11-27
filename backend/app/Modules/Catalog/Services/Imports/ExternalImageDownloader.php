<?php

namespace App\Modules\Catalog\Services\Imports;

use App\Modules\Catalog\Enums\MediaType;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

/**
 * Downloads one image (or gallery video) of an external catalog (product gallery, variant thumbnail,
 * category image) into a temporary UploadedFile for the normal media pipeline; null
 * when it cannot be read, is not an image or is too large. Callers delete the file.
 */
class ExternalImageDownloader
{
    public const TIMEOUT_SECONDS = 30;

    public const MAX_BYTES = 10_485_760;

    public const VIDEO_TIMEOUT_SECONDS = 120;

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

    /**
     * Downloads one gallery video (mp4 / webm / mov, #114) up to `$maxBytes`; the
     * type comes from the Content-Type, else from the URL extension (CDNs often answer
     * application/octet-stream). Null when unreadable, not a video or too large.
     */
    public function downloadVideo(string $url, string $name, int $maxBytes): ?UploadedFile
    {
        try {
            $response = Http::timeout(self::VIDEO_TIMEOUT_SECONDS)->retry(1, 500, throw: false)->get($url);
        } catch (ConnectionException) {
            return null;
        }

        $mime = strtolower(trim(explode(';', (string) $response->header('Content-Type'))[0]));
        $extension = strtolower(pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));

        if (! in_array($mime, MediaType::VIDEO_MIMES, true)) {
            $mime = MediaType::VIDEO_MIMES[$extension] ?? '';
        }

        $body = $response->body();

        if (! $response->successful() || $mime === '' || $body === '' || strlen($body) > $maxBytes) {
            return null;
        }

        $path = tempnam(sys_get_temp_dir(), 'catalog-import-');

        if ($path === false || file_put_contents($path, $body) === false) {
            return null;
        }

        return new UploadedFile($path, $name.'.'.MediaType::videoExtension($mime), $mime, null, true);
    }
}
