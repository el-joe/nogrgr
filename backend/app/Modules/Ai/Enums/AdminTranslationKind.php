<?php

namespace App\Modules\Ai\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * What an admin AI translation run fills (ADM-091b catalog / ADM-092a static keys), with
 * the permissions that see and run it and its usage-log feature.
 */
enum AdminTranslationKind: string
{
    use HasEnumHelpers;

    case Catalog = 'catalog';
    case StaticKeys = 'static_keys';

    public function viewPermission(): string
    {
        return match ($this) {
            self::Catalog => 'localization.languages.view',
            self::StaticKeys => 'localization.translations.view',
        };
    }

    public function managePermission(): string
    {
        return match ($this) {
            self::Catalog => 'localization.languages.manage',
            self::StaticKeys => 'localization.translations.manage',
        };
    }

    public function feature(): AiFeature
    {
        return match ($this) {
            self::Catalog => AiFeature::CatalogTranslation,
            self::StaticKeys => AiFeature::StaticKeyTranslation,
        };
    }

    /**
     * Progress sections of the kind, in display order.
     *
     * @return list<string>
     */
    public function sections(): array
    {
        return match ($this) {
            self::Catalog => array_map(static fn (CatalogTranslationSection $section): string => $section->value, CatalogTranslationSection::cases()),
            self::StaticKeys => ['static_keys'],
        };
    }
}
