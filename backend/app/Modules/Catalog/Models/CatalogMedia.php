<?php

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Enums\MediaConversionStatus;
use App\Modules\Catalog\Enums\MediaType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

/**
 * Gallery item (image or video, MediaType) of a central product (central DB, `public` disk under catalog/) or of a
 * vendor-own product (tenant DB, tenant-suffixed `public` disk). No fixed connection:
 * the relation inherits the owner's connection. Images get webp / avif derivatives
 * (ConvertCatalogMedia); videos an optional poster frame (GenerateVideoPoster) and are
 * never primary. Owners expose `media()` = images only and `gallery()` = everything.
 *
 * @property int $id
 * @property string $mediable_type
 * @property int $mediable_id
 * @property MediaType $type
 * @property string $disk
 * @property string $path
 * @property string|null $webp_path
 * @property string|null $avif_path
 * @property string|null $poster_path
 * @property string|null $mime_type
 * @property int $size
 * @property int|null $width
 * @property int|null $height
 * @property MediaConversionStatus $conversion_status
 * @property bool $is_primary
 * @property int $sort_order
 */
class CatalogMedia extends Model
{
    protected $table = 'catalog_media';

    /**
     * @var list<string>
     */
    protected $fillable = ['type', 'disk', 'path', 'poster_path', 'webp_path', 'avif_path', 'mime_type', 'size', 'width', 'height', 'conversion_status', 'is_primary', 'sort_order'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => MediaType::class,
            'conversion_status' => MediaConversionStatus::class,
            'is_primary' => 'boolean',
            'size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function mediable(): MorphTo
    {
        return $this->morphTo();
    }

    public function url(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    /**
     * URL to show: the WebP derivative once its conversion is done, otherwise (pending,
     * failed, skipped, no queue worker yet) the ORIGINAL file, so a fresh upload never
     * renders as a broken image. Videos always use the original.
     */
    public function bestUrl(): string
    {
        if (! $this->isVideo() && $this->conversion_status === MediaConversionStatus::Done && $this->webp_path !== null) {
            return Storage::disk($this->disk)->url($this->webp_path);
        }

        return $this->url();
    }

    public function isVideo(): bool
    {
        return $this->type === MediaType::Video;
    }

    public function posterUrl(): ?string
    {
        return $this->poster_path !== null ? Storage::disk($this->disk)->url($this->poster_path) : null;
    }

    /**
     * Every stored file of the item (original + derivatives + poster).
     *
     * @return list<string>
     */
    public function paths(): array
    {
        return array_values(array_filter([$this->path, $this->webp_path, $this->avif_path, $this->poster_path]));
    }

    public function avifUrl(): ?string
    {
        return $this->avif_path !== null ? Storage::disk($this->disk)->url($this->avif_path) : null;
    }

    public function webpUrl(): ?string
    {
        return $this->webp_path !== null ? Storage::disk($this->disk)->url($this->webp_path) : null;
    }
}
