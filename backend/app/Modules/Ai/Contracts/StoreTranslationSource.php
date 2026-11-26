<?php

namespace App\Modules\Ai\Contracts;

use App\Modules\Ai\DTOs\TranslationItemData;
use App\Modules\Ai\Enums\StoreTranslationSection;
use Illuminate\Database\Eloquent\Model;

/**
 * One section of the paid full-store translation run (decision #44). Every method runs
 * in the store's tenant context. Sources never touch central rows: central content is
 * translated into the store's override tables only (decision #65).
 */
interface StoreTranslationSource
{
    public function section(): StoreTranslationSection;

    /**
     * Item keys of the current store for the target locale.
     *
     * @return list<string>
     */
    public function keys(string $locale): array;

    /**
     * Items of the given keys. Fields locked by a hand translation are left out; fields
     * already filled in the target locale are left out unless the key is in
     * $retranslate (it was translated by an earlier run and its source changed).
     *
     * @param  list<string>  $keys
     * @param  array<string, true>  $retranslate
     * @return array<string, TranslationItemData>
     */
    public function items(array $keys, string $locale, array $retranslate = []): array;

    /**
     * Save translated fields of one item in the target locale.
     *
     * @param  array<string, string>  $translations  field => text
     */
    public function write(string $key, string $locale, array $translations): void;

    /**
     * Translatable models this source covers (architecture test: none may be missed).
     *
     * @return list<class-string<Model&AiTranslatable>>
     */
    public function models(): array;
}
