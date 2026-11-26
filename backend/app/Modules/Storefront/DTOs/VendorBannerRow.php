<?php

namespace App\Modules\Storefront\DTOs;

use App\Modules\Storefront\Enums\ContentSource;
use App\Modules\Storefront\Models\Banner;
use App\Modules\Storefront\Models\StoreBanner;

/**
 * One row of the vendor banners list (VEN-035): a store-owned banner (tenant DB) or a
 * central default banner that targets the store (central DB, read-only, may be hidden).
 */
final readonly class VendorBannerRow
{
    public function __construct(
        public ContentSource $source,
        public Banner|StoreBanner $banner,
        public bool $hidden = false,
    ) {}

    public function isCentral(): bool
    {
        return $this->source === ContentSource::Central;
    }

    public function title(): string
    {
        $title = $this->banner->title;

        return is_string($title) && trim($title) !== '' ? $title : '#'.$this->banner->id;
    }
}
