<?php

namespace App\Modules\Storefront\DTOs;

/**
 * Store CMS page input (VEN-033, P8 page): locale => value maps; body is HTML that the
 * service sanitises (allow-list).
 */
final readonly class StorePageData
{
    /**
     * @param  array<string, string>  $title
     * @param  array<string, string>  $body
     * @param  array<string, string>  $metaDescription
     */
    public function __construct(
        public array $title,
        public array $body = [],
        public array $metaDescription = [],
        public ?string $slug = null,
        public bool $isActive = true,
        public bool $showInFooter = true,
    ) {}
}
