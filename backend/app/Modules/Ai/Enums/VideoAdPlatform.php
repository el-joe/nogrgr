<?php

namespace App\Modules\Ai\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Step 3 "Platform and size": where the ad runs, its icon, the sizes it accepts (first =
 * default) and the caption rules sent to the model.
 */
enum VideoAdPlatform: string
{
    use HasEnumHelpers;

    case TikTok = 'tiktok';
    case InstagramReels = 'instagram_reels';
    case InstagramFeed = 'instagram_feed';
    case Facebook = 'facebook';
    case Snapchat = 'snapchat';
    case YouTubeShorts = 'youtube_shorts';
    case X = 'x';

    public function icon(): string
    {
        return match ($this) {
            self::TikTok => 'social-tiktok',
            self::InstagramReels, self::InstagramFeed => 'social-instagram',
            self::Facebook => 'social-facebook',
            self::Snapchat => 'social-snapchat',
            self::YouTubeShorts => 'social-youtube',
            self::X => 'social-x',
        };
    }

    /**
     * @return list<VideoAdAspect>
     */
    public function aspects(): array
    {
        return match ($this) {
            self::TikTok, self::InstagramReels, self::Snapchat, self::YouTubeShorts => [VideoAdAspect::Vertical],
            self::InstagramFeed => [VideoAdAspect::Portrait, VideoAdAspect::Square],
            self::Facebook => [VideoAdAspect::Square, VideoAdAspect::Portrait, VideoAdAspect::Vertical, VideoAdAspect::Landscape],
            self::X => [VideoAdAspect::Landscape, VideoAdAspect::Square],
        };
    }

    public function supports(VideoAdAspect $aspect): bool
    {
        return in_array($aspect, $this->aspects(), true);
    }

    public function maxHashtags(): int
    {
        return match ($this) {
            self::X, self::Snapchat => 3,
            self::TikTok, self::YouTubeShorts => 5,
            default => 10,
        };
    }

    public function maxCaption(): int
    {
        return match ($this) {
            self::X => 280,
            self::Snapchat => 250,
            self::YouTubeShorts => 100,
            default => 2200,
        };
    }
}
