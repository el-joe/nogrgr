<?php

namespace App\Modules\Catalog\Repositories\Contracts;

use App\Modules\Catalog\Models\Attribute;
use App\Modules\Catalog\Models\AttributeOption;
use App\Shared\Repositories\Contracts\RepositoryContract;

/**
 * @extends RepositoryContract<Attribute>
 */
interface AttributeRepositoryContract extends RepositoryContract
{
    /**
     * @return array{products: int, variants: int}
     */
    public function usage(Attribute $attribute): array;

    /**
     * Ids among the given options that are used by at least one variant.
     *
     * @param  list<int>  $optionIds
     * @return list<int>
     */
    public function optionsInUse(array $optionIds): array;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createOption(Attribute $attribute, array $attributes): AttributeOption;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateOption(AttributeOption $option, array $attributes): AttributeOption;

    /**
     * @param  list<int>  $optionIds
     */
    public function deleteOptions(Attribute $attribute, array $optionIds): void;

    public function nextSortOrder(): int;
}
