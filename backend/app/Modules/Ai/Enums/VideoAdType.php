<?php

namespace App\Modules\Ai\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Step 1 "Video type" of the AI Advertising Studio (VEN-010c, Figma 12:15119), with its
 * icon and the creative direction sent to the script / image prompts.
 */
enum VideoAdType: string
{
    use HasEnumHelpers;

    case ProductOnly = 'product_only';
    case Ugc = 'ugc';
    case Lifestyle = 'lifestyle';
    case ProductDemo = 'product_demo';
    case Unboxing = 'unboxing';
    case ProblemSolution = 'problem_solution';
    case AdStyle = 'ad_style';

    public function icon(): string
    {
        return match ($this) {
            self::ProductOnly => 'ad-video',
            self::Ugc => 'ad-user',
            self::Lifestyle => 'ad-magic',
            self::ProductDemo => 'ad-packaging',
            self::Unboxing => 'ad-package-open',
            self::ProblemSolution => 'ad-help',
            self::AdStyle => 'ad-playlist',
        };
    }

    public function direction(): string
    {
        return match ($this) {
            self::ProductOnly => 'Product-only hero ad: clean studio shots of the product alone, close-ups of details and materials, no people.',
            self::Ugc => 'User-generated-content style: an ordinary customer filming on a phone, authentic, casual, first-person testimonial feel.',
            self::Lifestyle => 'Lifestyle ad: the product used in real everyday life scenes that show the feeling of owning it.',
            self::ProductDemo => 'Product demo: show how the product works step by step and its key features in use.',
            self::Unboxing => 'Unboxing: opening the package, revealing the product and first impressions.',
            self::ProblemSolution => 'Problem / solution: open on a relatable everyday problem, then the product solves it.',
            self::AdStyle => 'Classic polished commercial: bold, cinematic, brand-style advertisement with a strong ending call to action.',
        };
    }
}
