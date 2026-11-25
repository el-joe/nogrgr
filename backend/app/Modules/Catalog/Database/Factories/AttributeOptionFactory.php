<?php

namespace App\Modules\Catalog\Database\Factories;

use App\Modules\Catalog\Models\Attribute;
use App\Modules\Catalog\Models\AttributeOption;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AttributeOption>
 */
class AttributeOptionFactory extends Factory
{
    protected $model = AttributeOption::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = Str::title(fake()->word());

        return [
            'attribute_id' => Attribute::factory(),
            'name' => ['en' => $name, 'ar' => 'خيار '.$name],
            'swatch' => null,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
