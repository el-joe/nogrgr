<?php

namespace App\Modules\Localization\DTOs;

/**
 * Outcome of copying / syncing the base static texts into one store.
 */
final readonly class StaticTextSyncResult
{
    /**
     * @param  list<string>  $newKeys  base keys the store did not have
     * @param  list<string>  $changedKeys  base keys whose source text changed since the store's copy
     * @param  list<string>  $translationLocales  purchased (non ar/en) locales to translate them into
     */
    public function __construct(
        public int $inserted,
        public int $updated,
        public array $newKeys,
        public array $changedKeys,
        public array $translationLocales,
    ) {}

    /**
     * @return list<string>
     */
    public function keysToTranslate(): array
    {
        return array_values(array_unique(array_merge($this->newKeys, $this->changedKeys)));
    }
}
