<?php

namespace App\Modules\Ai\Contracts;

use App\Modules\Ai\DTOs\TranslationItemData;

/**
 * One section of an admin AI translation run (ADM-091b / ADM-092a). Sources only ever
 * fill what is missing in the target locale: existing values are never sent nor
 * overwritten, locked static keys are skipped. Runs on the central connection.
 */
interface AdminTranslationSource
{
    public function section(): string;

    /**
     * Item keys that still miss at least one translatable text in $locale.
     *
     * @return list<string>
     */
    public function keys(string $locale): array;

    /**
     * Items of the given keys with only their missing texts (items with nothing left to
     * translate are left out).
     *
     * @param  list<string>  $keys
     * @return array<string, TranslationItemData>
     */
    public function items(array $keys, string $locale): array;

    /**
     * Save translations; a field that got a value meanwhile is kept as is.
     *
     * @param  array<string, array<string, string>>  $translations  item key => field => text
     */
    public function write(string $locale, array $translations): void;
}
