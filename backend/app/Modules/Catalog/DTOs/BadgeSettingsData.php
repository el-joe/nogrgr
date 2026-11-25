<?php

namespace App\Modules\Catalog\DTOs;

use App\Modules\Catalog\Enums\BadgeAutoFill;
use App\Shared\DTOs\BaseDTO;

/**
 * Badge settings card: translated label, ribbon on cards (is_active) and auto-fill rule.
 */
final class BadgeSettingsData extends BaseDTO
{
    /**
     * @param  array<string, string>  $name
     */
    public function __construct(
        public readonly array $name,
        public readonly bool $isActive,
        public readonly BadgeAutoFill $autoFill,
        public readonly int $autoFillLimit,
    ) {}
}
