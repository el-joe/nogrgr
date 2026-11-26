<?php

namespace App\Modules\Storefront\DTOs;

/**
 * One storefront banner from BannerCatalog (central ⊕ store, decision #65 b), texts in the
 * requested locale. `source` = 'central' | 'vendor'.
 */
final readonly class BannerData
{
    public function __construct(
        public int $id,
        public string $source,
        public string $placement,
        public ?string $title,
        public ?string $subtitle,
        public string $imageUrl,
        public ?string $mobileImageUrl,
        public ?string $url,
        public ?string $ctaLabel,
        public ?string $alt,
        public ?int $width,
        public ?int $height,
        public int $sortOrder,
        public bool $hidden = false,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'source' => $this->source,
            'placement' => $this->placement,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'image_url' => $this->imageUrl,
            'mobile_image_url' => $this->mobileImageUrl,
            'url' => $this->url,
            'cta_label' => $this->ctaLabel,
            'alt' => $this->alt,
            'width' => $this->width,
            'height' => $this->height,
        ];
    }
}
