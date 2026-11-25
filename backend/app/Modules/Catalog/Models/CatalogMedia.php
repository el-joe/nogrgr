<?php

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Enums\MediaConversionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

/**
 * Gallery image of a central product (central DB, `public` disk under catalog/) or of a
 * vendor-own product (tenant DB, tenant-suffixed `public` disk). No fixed connection:
 * the relation inherits the owner's connection. AVIF derivative by ConvertMediaToAvif.
 *
 * @property int $id
 * @property string $mediable_type
 * @property int $mediable_id
 * @property string $disk
 * @property string $path
 * @property string|null $webp_path
 * @property string|null $avif_path
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
    protected $fillable = ['disk', 'path', 'webp_path', 'avif_path', 'mime_type', 'size', 'width', 'height', 'conversion_status', 'is_primary', 'sort_order'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
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

    public function avifUrl(): ?string
    {
        return $this->avif_path !== null ? Storage::disk($this->disk)->url($this->avif_path) : null;
    }

    public function webpUrl(): ?string
    {
        return $this->webp_path !== null ? Storage::disk($this->disk)->url($this->webp_path) : null;
    }
}
