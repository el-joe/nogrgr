<?php

namespace App\Modules\Platform\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * The 4 steps of the vendor setup wizard (VEN-007, Figma 82:99932), in order.
 */
enum SetupWizardStep: string
{
    use HasEnumHelpers;

    case Countries = 'countries';
    case Languages = 'languages';
    case Theme = 'theme';
    case Launch = 'launch';

    public function number(): int
    {
        return array_search($this, self::cases(), true) + 1;
    }

    public function next(): ?self
    {
        return self::cases()[$this->number()] ?? null;
    }

    public function icon(): string
    {
        return match ($this) {
            self::Countries => 'key',
            self::Languages => 'languages',
            self::Theme => 'palette',
            self::Launch => 'sparkles',
        };
    }
}
