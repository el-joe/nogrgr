<?php

namespace App\Modules\Localization\Support;

use App\Modules\Localization\Enums\TranslationFileKind;
use App\Modules\Localization\Services\StaticTexts\StaticTextCatalog;

/**
 * One editable translation "file" (ADM-092). Ids:
 *   "storefront"        storefront static text base set (copied into every store)
 *   "json"              lang/{locale}.json
 *   "<group>"           lang/{locale}/<group>.php
 *   "<module>::<group>" app/Modules/<Module>/resources/lang/{locale}/<group>.php
 */
final readonly class TranslationFile
{
    public const JSON = 'json';

    private const GROUP = '[a-z0-9][a-z0-9_-]{0,47}';

    private const NAMESPACE = '[a-z0-9][a-z0-9-]{0,40}';

    public function __construct(
        public string $id,
        public TranslationFileKind $kind,
        public ?string $namespace,
        public ?string $group,
    ) {}

    /**
     * Parse an id; null when it is not a valid file id.
     */
    public static function fromId(string $id): ?self
    {
        if ($id === StaticTextCatalog::FILE) {
            return new self($id, TranslationFileKind::Storefront, null, null);
        }

        if ($id === self::JSON) {
            return new self($id, TranslationFileKind::Json, null, null);
        }

        if (preg_match('/^(?:('.self::NAMESPACE.')::)?('.self::GROUP.')$/', $id, $matches) !== 1) {
            return null;
        }

        $namespace = $matches[1] !== '' ? $matches[1] : null;

        return new self($id, TranslationFileKind::Php, $namespace, $matches[2]);
    }

    /**
     * File id of a translator load (namespace "*" = app lang, group "*" = JSON).
     */
    public static function idFor(string $group, ?string $namespace): string
    {
        if ($group === '*' && ($namespace === null || $namespace === '*')) {
            return self::JSON;
        }

        return $namespace === null || $namespace === '*' ? $group : $namespace.'::'.$group;
    }

    public static function isValidGroup(string $group): bool
    {
        return preg_match('/^'.self::GROUP.'$/', $group) === 1;
    }

    public function isStorefront(): bool
    {
        return $this->kind === TranslationFileKind::Storefront;
    }

    public function label(): string
    {
        return match ($this->kind) {
            TranslationFileKind::Storefront => __('localization::translations.files.storefront'),
            TranslationFileKind::Json => __('localization::translations.files.json'),
            TranslationFileKind::Php => $this->namespace === null ? $this->group.'.php' : $this->namespace.' › '.$this->group.'.php',
        };
    }
}
