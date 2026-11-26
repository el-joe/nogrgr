<?php

namespace App\Modules\Ai\Repositories\Contracts;

use App\Modules\Ai\Enums\StoreTranslationSection;
use App\Modules\Ai\Models\StoreTranslationItem;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Database\Eloquent\Collection;

/**
 * Tenant DB (current store).
 *
 * @extends RepositoryContract<StoreTranslationItem>
 */
interface StoreTranslationItemRepositoryContract extends RepositoryContract
{
    /**
     * Source hash of every done item among $keys (key => hash).
     *
     * @param  list<string>  $keys
     * @return array<string, string>
     */
    public function doneHashes(StoreTranslationSection $section, string $locale, array $keys): array;

    public function markDone(int $runId, StoreTranslationSection $section, string $key, string $locale, string $hash): void;

    public function markFailed(int $runId, StoreTranslationSection $section, string $key, string $locale, string $error): void;

    /**
     * Done / failed item counts per section in one locale.
     *
     * @return array<string, array{done: int, failed: int}>
     */
    public function countsBySection(string $locale): array;

    /**
     * Failed items of one locale (latest first), for the VEN-074 failed-items list.
     *
     * @return Collection<int, StoreTranslationItem>
     */
    public function failedItems(string $locale, int $limit = 50): Collection;
}
