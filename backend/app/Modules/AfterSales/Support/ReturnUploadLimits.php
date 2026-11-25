<?php

namespace App\Modules\AfterSales\Support;

/**
 * Upload limits of customer return evidence and replies (storefront account spec:
 * photos ≤ 5 MB, video ≤ 50 MB). Fixed product rules, not environment settings.
 */
final class ReturnUploadLimits
{
    public const MAX_PHOTOS = 5;

    /** Kilobytes (Laravel "max" rule unit). */
    public const PHOTO_MAX_KB = 5120;

    /** @var list<string> */
    public const PHOTO_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    public const MAX_VIDEOS = 1;

    /** Kilobytes (Laravel "max" rule unit). */
    public const VIDEO_MAX_KB = 51200;

    /** @var list<string> */
    public const VIDEO_EXTENSIONS = ['mp4', 'mov', 'webm'];

    /**
     * @return array<string, mixed>
     */
    public static function toArray(): array
    {
        return [
            'photos' => ['max_files' => self::MAX_PHOTOS, 'max_size_kb' => self::PHOTO_MAX_KB, 'extensions' => self::PHOTO_EXTENSIONS],
            'video' => ['max_files' => self::MAX_VIDEOS, 'max_size_kb' => self::VIDEO_MAX_KB, 'extensions' => self::VIDEO_EXTENSIONS],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(): array
    {
        return [
            'photos' => ['nullable', 'array', 'max:'.self::MAX_PHOTOS],
            'photos.*' => ['file', 'mimes:'.implode(',', self::PHOTO_EXTENSIONS), 'max:'.self::PHOTO_MAX_KB],
            'video' => ['nullable', 'file', 'mimes:'.implode(',', self::VIDEO_EXTENSIONS), 'max:'.self::VIDEO_MAX_KB],
        ];
    }
}
