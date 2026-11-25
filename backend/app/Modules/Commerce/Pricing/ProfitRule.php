<?php

namespace App\Modules\Commerce\Pricing;

use App\Modules\Commerce\Enums\ProfitType;
use JsonSerializable;

/**
 * One vendor profit row: {profit_type, profit_value} as stored in the per-country
 * `profit` JSON (keys = country id or "default"). Negative values are clamped to 0.
 */
final readonly class ProfitRule implements JsonSerializable
{
    public float $value;

    public function __construct(public ProfitType $type, float $value)
    {
        $this->value = max(0.0, $value);
    }

    public static function percentage(float $value): self
    {
        return new self(ProfitType::Percentage, $value);
    }

    public static function fixed(float $value): self
    {
        return new self(ProfitType::Fixed, $value);
    }

    /**
     * @param  array<string, mixed>  $row  {profit_type, profit_value, total_profit?}
     */
    public static function fromArray(array $row): self
    {
        return new self(ProfitType::fromLegacy($row['profit_type'] ?? null), (float) ($row['profit_value'] ?? 0));
    }

    /**
     * @return array{profit_type: string, profit_value: float}
     */
    public function jsonSerialize(): array
    {
        return ['profit_type' => $this->type->value, 'profit_value' => $this->value];
    }
}
