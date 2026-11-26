<?php

namespace App\Modules\Ai\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Step 2 "People and voice": voice-over of the ad (OpenAI text-to-speech voice) or none
 * (on-screen captions only).
 */
enum VideoAdVoice: string
{
    use HasEnumHelpers;

    case None = 'none';
    case Female = 'female';
    case Male = 'male';

    /**
     * OpenAI TTS voice id (null = no voice-over).
     */
    public function openAiVoice(): ?string
    {
        return match ($this) {
            self::None => null,
            self::Female => 'nova',
            self::Male => 'onyx',
        };
    }
}
