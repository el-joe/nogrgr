<?php

namespace App\Modules\Ai\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Step 2 "People and voice": tone of the script and the voice-over.
 */
enum VideoAdTone: string
{
    use HasEnumHelpers;

    case Energetic = 'energetic';
    case Friendly = 'friendly';
    case Luxury = 'luxury';
    case Calm = 'calm';
    case Funny = 'funny';

    public function prompt(): string
    {
        return match ($this) {
            self::Energetic => 'energetic, upbeat and exciting',
            self::Friendly => 'warm, friendly and conversational',
            self::Luxury => 'elegant, premium and confident',
            self::Calm => 'calm, reassuring and clear',
            self::Funny => 'light-hearted and witty, never offensive',
        };
    }
}
