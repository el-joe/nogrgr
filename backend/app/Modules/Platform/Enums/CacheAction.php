<?php

namespace App\Modules\Platform\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Admin › System tools › Cache tools (ADM-114a main, ADM-114b tenants).
 */
enum CacheAction: string
{
    use HasEnumHelpers;

    // Main cache (central application)
    case Application = 'application';
    case ConfigRoutes = 'config_routes';
    case Views = 'views';
    case StaticTexts = 'static_texts';

    // One store
    case StorePages = 'store_pages';
    case StoreTranslations = 'store_translations';
    case StoreThemes = 'store_themes';

    // Every store (queued)
    case AllStores = 'all_stores';

    /**
     * @return list<self>
     */
    public static function main(): array
    {
        return [self::Application, self::ConfigRoutes, self::Views, self::StaticTexts];
    }

    /**
     * @return list<self>
     */
    public static function store(): array
    {
        return [self::StorePages, self::StoreTranslations, self::StoreThemes];
    }

    public function needsStore(): bool
    {
        return in_array($this, self::store(), true);
    }

    /**
     * Runs on the queue (touches every store).
     */
    public function queued(): bool
    {
        return in_array($this, [self::StaticTexts, self::AllStores], true);
    }

    public function icon(): string
    {
        return match ($this) {
            self::Application => 'server',
            self::ConfigRoutes => 'layers',
            self::Views => 'file',
            self::StaticTexts, self::StoreTranslations => 'languages',
            self::StorePages => 'store',
            self::StoreThemes => 'palette',
            self::AllStores => 'trash',
        };
    }
}
