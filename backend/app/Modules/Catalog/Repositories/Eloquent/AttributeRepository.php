<?php

namespace App\Modules\Catalog\Repositories\Eloquent;

use App\Modules\Catalog\Models\Attribute;
use App\Modules\Catalog\Models\AttributeOption;
use App\Modules\Catalog\Repositories\Contracts\AttributeRepositoryContract;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Support\Facades\DB;

/**
 * @extends BaseRepository<Attribute>
 */
class AttributeRepository extends BaseRepository implements AttributeRepositoryContract
{
    protected function model(): string
    {
        return Attribute::class;
    }

    public function usage(Attribute $attribute): array
    {
        $connection = $attribute->getConnectionName();

        return [
            'products' => DB::connection($connection)->table('product_attribute')->where('attribute_id', $attribute->id)->count(),
            'variants' => DB::connection($connection)->table('product_variant_option')
                ->join('attribute_options', 'attribute_options.id', '=', 'product_variant_option.attribute_option_id')
                ->where('attribute_options.attribute_id', $attribute->id)
                ->distinct()
                ->count('product_variant_option.product_variant_id'),
        ];
    }

    public function optionsInUse(array $optionIds): array
    {
        if ($optionIds === []) {
            return [];
        }

        return array_map('intval', DB::connection((new Attribute)->getConnectionName())->table('product_variant_option')
            ->whereIn('attribute_option_id', $optionIds)
            ->distinct()
            ->pluck('attribute_option_id')
            ->all());
    }

    public function createOption(Attribute $attribute, array $attributes): AttributeOption
    {
        /** @var AttributeOption $option */
        $option = $attribute->options()->create($attributes);

        return $option;
    }

    public function updateOption(AttributeOption $option, array $attributes): AttributeOption
    {
        $option->fill($attributes)->save();

        return $option;
    }

    public function deleteOptions(Attribute $attribute, array $optionIds): void
    {
        if ($optionIds !== []) {
            $attribute->options()->whereKey($optionIds)->delete();
        }
    }

    public function nextSortOrder(): int
    {
        $max = $this->query()->max('sort_order');

        return $max === null ? 0 : (int) $max + 1;
    }
}
