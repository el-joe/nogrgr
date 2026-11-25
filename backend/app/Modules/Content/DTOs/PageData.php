<?php

namespace App\Modules\Content\DTOs;

use App\Modules\Content\Enums\PageStatus;
use App\Modules\Content\Enums\PageTarget;
use App\Shared\DTOs\BaseDTO;

/**
 * Static page form (ADM-082a).
 */
final class PageData extends BaseDTO
{
    /**
     * @param  array<string, string>  $title
     * @param  array<string, string>  $body  sanitised HTML
     * @param  array<string, string>  $metaTitle
     * @param  array<string, string>  $metaDescription
     */
    public function __construct(
        public readonly string $slug,
        public readonly array $title,
        public readonly array $body,
        public readonly array $metaTitle,
        public readonly array $metaDescription,
        public readonly PageTarget $target,
        public readonly bool $isCompliance,
        public readonly PageStatus $status,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        return [
            'slug' => $this->slug,
            'title' => $this->title,
            'body' => $this->body,
            'meta_title' => $this->metaTitle,
            'meta_description' => $this->metaDescription,
            'target' => $this->target,
            'is_compliance' => $this->isCompliance,
            'status' => $this->status,
        ];
    }
}
