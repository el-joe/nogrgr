<?php

namespace App\Modules\Catalog\Database\Factories;

use App\Modules\Catalog\Enums\AttributeDisplayType;
use App\Modules\Catalog\Enums\RecordStatus;
use App\Modules\Catalog\Models\Attribute;
use App\Modules\Catalog\Models\AttributeOption;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Attribute>
 */
class AttributeFactory extends Factory
{
    protected $model = Attribute::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = Str::title(fake()->unique()->word());

        return [
            'name' => ['en' => $name, 'ar' => 'سمة '.$name],
            'display_type' => AttributeDisplayType::Text,
            'status' => RecordStatus::Active,
            'sort_order' => 0,
        ];
    }

    public function color(): static
    {
        return $this->state(['display_type' => AttributeDisplayType::Color]);
    }

    /**
     * With the given option names (English), in order.
     *
     * @param  list<string>  $names
     */
    public function withOptions(array $names = ['Small', 'Medium', 'Large']): static
    {
        return $this->afterCreating(function (Attribute $attribute) use ($names): void {
            foreach (array_values($names) as $position => $name) {
                AttributeOption::factory()->for($attribute)->create([
                    'name' => ['en' => $name, 'ar' => $name],
                    'sort_order' => $position,
                ]);
            }
        });
    }
}
