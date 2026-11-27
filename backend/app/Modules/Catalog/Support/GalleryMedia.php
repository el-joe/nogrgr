<?php

namespace App\Modules\Catalog\Support;

use App\Modules\Catalog\Enums\MediaType;
use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;

/**
 * Validation of one product gallery upload (#114): an image with the form's image rules,
 * or a video (mp4 / webm / mov) up to Admin › Settings › Platform › Commerce
 * "product video size limit" (`commerce.product_video_max_mb`, DB setting, decision #69).
 */
final class GalleryMedia
{
    public const DEFAULT_VIDEO_MAX_MB = 50;

    public static function videoMaxMegabytes(): int
    {
        $value = (int) config('commerce.product_video_max_mb', self::DEFAULT_VIDEO_MAX_MB);

        return $value > 0 ? $value : self::DEFAULT_VIDEO_MAX_MB;
    }

    public static function videoMaxBytes(): int
    {
        return self::videoMaxMegabytes() * 1024 * 1024;
    }

    /**
     * Rule list for `images.*`: images keep `$imageRules`, videos get the video rules.
     *
     * @param  list<mixed>  $imageRules
     * @return list<mixed>
     */
    public static function rules(array $imageRules): array
    {
        return ['file', static function (string $attribute, mixed $value, Closure $fail) use ($imageRules): void {
            if (! $value instanceof UploadedFile) {
                return;
            }

            $video = MediaType::fromUpload($value) === MediaType::Video;
            $rules = $video
                ? ['file', 'mimetypes:'.implode(',', array_values(MediaType::VIDEO_MIMES)), 'extensions:'.implode(',', array_keys(MediaType::VIDEO_MIMES)), 'max:'.(self::videoMaxMegabytes() * 1024)]
                : $imageRules;
            $validator = Validator::make(['file' => $value], ['file' => $rules], [], ['file' => __('catalog::products.fields.media_file')]);

            if ($validator->fails()) {
                $fail((string) $validator->errors()->first('file'));
            }
        }];
    }
}
