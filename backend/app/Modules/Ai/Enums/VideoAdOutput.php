<?php

namespace App\Modules\Ai\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * What the generator produced: an MP4 assembled with ffmpeg, or — when ffmpeg is not
 * available on the server — an animated slideshow (scene images + voice-over track,
 * downloadable as a ZIP).
 */
enum VideoAdOutput: string
{
    use HasEnumHelpers;

    case Video = 'video';
    case Slideshow = 'slideshow';
}
