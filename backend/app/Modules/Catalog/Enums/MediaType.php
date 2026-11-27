<?php

namespace App\Modules\Catalog\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;
use Illuminate\Http\UploadedFile;

/**
 * Kind of a product gallery item (catalog_media.type). Videos sit in the gallery order
 * next to the images but are never the primary item.
 */
enum MediaType: string
{
    use HasEnumHelpers;

    case Image = 'image';
    case Video = 'video';

    /** Accepted video containers (extension => mime). */
    public const VIDEO_MIMES = ['mp4' => 'video/mp4', 'webm' => 'video/webm', 'mov' => 'video/quicktime'];

    public static function fromMime(?string $mime): self
    {
        return is_string($mime) && str_starts_with(strtolower($mime), 'video/') ? self::Video : self::Image;
    }

    public static function fromUpload(UploadedFile $file): self
    {
        $mime = $file->getMimeType() ?: $file->getClientMimeType();

        return self::fromMime($mime) === self::Video || in_array(strtolower($file->getClientOriginalExtension()), array_keys(self::VIDEO_MIMES), true)
            ? self::Video
            : self::Image;
    }

    /**
     * File extension for a stored video of the given mime type.
     */
    public static function videoExtension(?string $mime): string
    {
        $extension = array_search(strtolower((string) $mime), self::VIDEO_MIMES, true);

        return is_string($extension) ? $extension : 'mp4';
    }

    /**
     * `accept` attribute of the gallery uploader for videos.
     */
    public static function videoAccept(): string
    {
        return implode(',', array_values(self::VIDEO_MIMES));
    }
}
