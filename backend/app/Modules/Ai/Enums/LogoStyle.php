<?php

namespace App\Modules\Ai\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Styles offered by the AI logo generator (VEN-034a) with the prompt wording.
 */
enum LogoStyle: string
{
    use HasEnumHelpers;

    case ModernMinimal = 'modern_minimal';
    case Playful = 'playful';
    case Elegant = 'elegant';
    case Bold = 'bold';
    case Vintage = 'vintage';
    case Tech = 'tech';

    public function prompt(): string
    {
        return match ($this) {
            self::ModernMinimal => 'modern minimal flat design, clean geometric mark, generous white space',
            self::Playful => 'playful friendly design, rounded shapes, cheerful',
            self::Elegant => 'elegant luxury design, refined thin lines, serif lettering',
            self::Bold => 'bold strong design, heavy shapes, high contrast',
            self::Vintage => 'vintage badge style, classic emblem, retro lettering',
            self::Tech => 'futuristic tech design, sharp geometric lines, digital feel',
        };
    }
}
