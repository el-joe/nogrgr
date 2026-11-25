<?php

namespace App\Modules\Localization\Repositories\Contracts;

use App\Modules\Localization\Models\Language;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends RepositoryContract<Language>
 */
interface LanguageRepositoryContract extends RepositoryContract
{
    public function findByCode(string $code): ?Language;

    public function findActiveByCode(string $code): ?Language;

    /**
     * Active languages ordered for display.
     *
     * @return Collection<int, Language>
     */
    public function active(): Collection;

    /**
     * Insert seed rows; existing rows only get their reference data refreshed
     * (`is_active`, prices, free flag, countries and sort stay as the admin set them).
     *
     * @param  list<array<string, mixed>>  $rows
     */
    public function upsertMany(array $rows): int;
}
