<?php

namespace App\Modules\Catalog\DTOs;

use App\Shared\DTOs\BaseDTO;

/**
 * One row of the attribute options repeater (`id` null = new option). Order = position.
 */
final class AttributeOptionData extends BaseDTO
{
    /**
     * @param  array<string, string>  $name
     */
    public function __construct(
        public readonly ?int $id,
        public readonly array $name,
        public readonly ?string $swatch,
        public readonly bool $isActive = true,
    ) {}
}
