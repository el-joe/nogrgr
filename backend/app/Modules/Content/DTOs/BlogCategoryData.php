<?php

namespace App\Modules\Content\DTOs;

use App\Shared\DTOs\BaseDTO;

/**
 * Blog category form (ADM-080a).
 */
final class BlogCategoryData extends BaseDTO
{
    /**
     * @param  array<string, string>  $name
     * @param  array<string, string>  $description
     */
    public function __construct(
        public readonly string $slug,
        public readonly array $name,
        public readonly array $description,
        public readonly bool $isActive,
        public readonly int $sortOrder,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'is_active' => $this->isActive,
            'sort_order' => $this->sortOrder,
        ];
    }
}
