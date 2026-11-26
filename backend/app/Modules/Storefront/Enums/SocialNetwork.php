<?php

namespace App\Modules\Storefront\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * A social profile the store links to (Appearance › Social links, VEN-034f): shown as
 * icons in every storefront footer. Each network accepts only its own host(s).
 */
enum SocialNetwork: string
{
    use HasEnumHelpers;

    case Instagram = 'instagram';
    case Facebook = 'facebook';
    case Tiktok = 'tiktok';
    case X = 'x';
    case Youtube = 'youtube';
    case Snapchat = 'snapchat';
    case Whatsapp = 'whatsapp';
    case Linkedin = 'linkedin';
    case Telegram = 'telegram';
    case Pinterest = 'pinterest';

    public function icon(): string
    {
        return 'social-'.$this->value;
    }

    /**
     * Hosts a profile URL of this network may use (sub-domains included).
     *
     * @return list<string>
     */
    public function hosts(): array
    {
        return match ($this) {
            self::Instagram => ['instagram.com'],
            self::Facebook => ['facebook.com', 'fb.com', 'fb.me'],
            self::Tiktok => ['tiktok.com'],
            self::X => ['x.com', 'twitter.com'],
            self::Youtube => ['youtube.com', 'youtu.be'],
            self::Snapchat => ['snapchat.com'],
            self::Whatsapp => ['wa.me', 'whatsapp.com'],
            self::Linkedin => ['linkedin.com'],
            self::Telegram => ['t.me', 'telegram.me'],
            self::Pinterest => ['pinterest.com', 'pin.it'],
        };
    }

    public function placeholder(): string
    {
        return match ($this) {
            self::Whatsapp => 'https://wa.me/966500000000',
            self::Telegram => 'https://t.me/yourstore',
            default => 'https://'.$this->hosts()[0].'/yourstore',
        };
    }

    public function accepts(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        foreach ($this->hosts() as $allowed) {
            if ($host === $allowed || str_ends_with($host, '.'.$allowed)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Labels live in the Content module's panel translations: the Storefront module ships no
     * lang directory (decision #45, StorefrontStaticTextsTest).
     */
    public static function translationKey(): string
    {
        return 'content::store_enums.social_network';
    }
}
