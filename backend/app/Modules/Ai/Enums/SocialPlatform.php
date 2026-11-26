<?php

namespace App\Modules\Ai\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Platforms of the AI social posts generator (VEN-010c), with the caption rules sent to
 * the model.
 */
enum SocialPlatform: string
{
    use HasEnumHelpers;

    case Instagram = 'instagram';
    case TikTok = 'tiktok';
    case X = 'x';
    case Snapchat = 'snapchat';

    public function maxCaption(): int
    {
        return match ($this) {
            self::Instagram => 2200,
            self::TikTok => 2200,
            self::X => 280,
            self::Snapchat => 250,
        };
    }

    public function maxHashtags(): int
    {
        return match ($this) {
            self::Instagram => 12,
            self::TikTok => 6,
            self::X => 3,
            self::Snapchat => 3,
        };
    }

    public function style(): string
    {
        return match ($this) {
            self::Instagram => 'Instagram feed post: warm storytelling hook, 2-4 short lines, emojis allowed, call to action to shop via the link in bio.',
            self::TikTok => 'TikTok video caption: punchy, trend-aware first line, very short, one call to action.',
            self::X => 'X (Twitter) post: one or two crisp sentences, must fit 280 characters INCLUDING hashtags and the link.',
            self::Snapchat => 'Snapchat story text: casual, very short, playful.',
        };
    }
}
