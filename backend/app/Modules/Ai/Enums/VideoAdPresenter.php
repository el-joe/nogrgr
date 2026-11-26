<?php

namespace App\Modules\Ai\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Step 2 "People and voice": who appears in the generated scenes.
 */
enum VideoAdPresenter: string
{
    use HasEnumHelpers;

    case None = 'none';
    case Woman = 'woman';
    case Man = 'man';
    case Hands = 'hands';

    public function icon(): string
    {
        return match ($this) {
            self::None => 'ad-video',
            self::Woman, self::Man => 'ad-user',
            self::Hands => 'ad-packaging',
        };
    }

    public function prompt(): string
    {
        return match ($this) {
            self::None => 'No people in the frame.',
            self::Woman => 'A friendly woman in her late twenties presents and uses the product.',
            self::Man => 'A friendly man in his late twenties presents and uses the product.',
            self::Hands => 'Only hands are visible holding and using the product, no faces.',
        };
    }
}
