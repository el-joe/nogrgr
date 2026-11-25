<?php

namespace App\Modules\Catalog\DTOs;

use App\Modules\Catalog\Enums\AttributeDisplayType;
use App\Modules\Catalog\Enums\RecordStatus;
use App\Shared\DTOs\BaseDTO;

/**
 * Admin create / edit of a variant attribute with its ordered options.
 */
final class AttributeData extends BaseDTO
{
    /**
     * @param  array<string, string>  $name
     * @param  list<AttributeOptionData>  $options
     */
    public function __construct(
        public readonly array $name,
        public readonly AttributeDisplayType $displayType,
        public readonly RecordStatus $status,
        public readonly array $options = [],
    ) {}
}
