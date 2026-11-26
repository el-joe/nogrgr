<?php

namespace App\Modules\Ai\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Colour palettes offered by the AI logo generator (VEN-034a).
 */
enum LogoColor: string
{
    use HasEnumHelpers;

    case Orange = 'orange';
    case Black = 'black';
    case Blue = 'blue';
    case Green = 'green';
    case Red = 'red';
    case Purple = 'purple';
    case Gold = 'gold';
    case Colorful = 'colorful';

    public function prompt(): string
    {
        return match ($this) {
            self::Colorful => 'a vibrant multi-colour palette',
            self::Black => 'black and white only',
            default => 'a '.$this->value.' colour palette with neutral accents',
        };
    }
}
