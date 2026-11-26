<?php

namespace App\Modules\Ai\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Step 3 "Platform and size": frame size of the rendered ad (video pixels) and the
 * closest OpenAI image size for its scenes.
 */
enum VideoAdAspect: string
{
    use HasEnumHelpers;

    case Vertical = '9:16';
    case Square = '1:1';
    case Portrait = '4:5';
    case Landscape = '16:9';

    /**
     * @return array{0: int, 1: int} width, height (even numbers for H.264)
     */
    public function dimensions(): array
    {
        return match ($this) {
            self::Vertical => [720, 1280],
            self::Square => [1080, 1080],
            self::Portrait => [864, 1080],
            self::Landscape => [1280, 720],
        };
    }

    public function imageSize(): string
    {
        return match ($this) {
            self::Vertical, self::Portrait => '1024x1536',
            self::Square => '1024x1024',
            self::Landscape => '1536x1024',
        };
    }

    public function cssClass(): string
    {
        return match ($this) {
            self::Vertical => 'aspect-[9/16]',
            self::Square => 'aspect-square',
            self::Portrait => 'aspect-[4/5]',
            self::Landscape => 'aspect-video',
        };
    }
}
