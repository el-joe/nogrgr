<?php

namespace App\Modules\Localization\Repositories\Contracts;

use App\Modules\Localization\Models\TenantStaticText;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Database\Eloquent\Collection;

/**
 * Tenant DB (current store).
 *
 * @extends RepositoryContract<TenantStaticText>
 */
interface TenantStaticTextRepositoryContract extends RepositoryContract
{
    /**
     * key => value of one locale.
     *
     * @return array<string, string>
     */
    public function valuesFor(string $locale): array;

    /**
     * Rows of one locale keyed by key.
     *
     * @return Collection<string, TenantStaticText>
     */
    public function keyedFor(string $locale): Collection;

    public function findEntry(string $key, string $locale): ?TenantStaticText;

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function insertMany(array $rows): void;

    /**
     * @return list<string>
     */
    public function locales(): array;
}
