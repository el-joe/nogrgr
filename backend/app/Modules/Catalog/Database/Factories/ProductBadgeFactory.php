<?php

namespace App\Modules\Catalog\Database\Factories;

use App\Modules\Catalog\Enums\BadgeAutoFill;
use App\Modules\Catalog\Enums\BadgeType;
use App\Modules\Catalog\Models\ProductBadge;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductBadge>
 */
class ProductBadgeFactory extends Factory
{
    protected $model = ProductBadge::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return $this->forType(BadgeType::NewIn);
    }

    public function type(BadgeType $type): static
    {
        return $this->state($this->forType($type));
    }

    /**
     * @return array<string, mixed>
     */
    private function forType(BadgeType $type): array
    {
        return [
            'type' => $type,
            'name' => ['en' => $type->label(), 'ar' => $type->label()],
            'is_active' => true,
            'auto_fill' => BadgeAutoFill::Manual,
            'auto_fill_limit' => 24,
            'sort_order' => array_search($type, BadgeType::cases(), true),
        ];
    }
}
