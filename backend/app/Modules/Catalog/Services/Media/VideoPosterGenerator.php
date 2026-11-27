<?php

namespace App\Modules\Catalog\Services\Media;

use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Poster frame (JPEG) of a gallery video, taken with ffmpeg at 1 s (first frame for
 * shorter clips) and stored next to the video on the same disk. Without an executable
 * ffmpeg at FFMPEG the video simply has no poster (#114).
 */
class VideoPosterGenerator
{
    public const FFMPEG = '/usr/bin/ffmpeg';

    public const TIMEOUT_SECONDS = 60;

    public function available(): bool
    {
        return is_executable(self::FFMPEG);
    }

    /**
     * Stored poster path, or null when ffmpeg is missing or produced no frame.
     */
    public function generate(string $disk, string $path): ?string
    {
        if (! $this->available()) {
            return null;
        }

        $storage = Storage::disk($disk);
        $stream = $storage->readStream($path);

        if (! is_resource($stream)) {
            return null;
        }

        $source = tempnam(sys_get_temp_dir(), 'catalog-video-');
        $target = $source !== false ? $source.'.jpg' : false;

        if ($source === false || $target === false) {
            return null;
        }

        try {
            $copy = fopen($source, 'wb');

            if ($copy === false) {
                return null;
            }

            stream_copy_to_stream($stream, $copy);
            fclose($copy);

            foreach (['1', '0'] as $offset) {
                $result = Process::timeout(self::TIMEOUT_SECONDS)->run([
                    self::FFMPEG, '-y', '-hide_banner', '-loglevel', 'error', '-ss', $offset, '-i', $source,
                    '-frames:v', '1', '-vf', 'scale=min(1280\,iw):-2', '-q:v', '3', $target,
                ]);

                if ($result->successful() && is_file($target) && filesize($target) > 0) {
                    $posterPath = Str::beforeLast($path, '.').'-poster.jpg';

                    return $storage->put($posterPath, (string) file_get_contents($target)) ? $posterPath : null;
                }
            }

            return null;
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }

            @unlink($source);
            @unlink($target);
        }
    }
}
