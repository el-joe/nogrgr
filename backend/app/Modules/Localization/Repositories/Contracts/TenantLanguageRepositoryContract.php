<?php

namespace App\Modules\Localization\Repositories\Contracts;

use App\Modules\Localization\Models\TenantLanguage;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Database\Eloquent\Collection;

/**
 * Tenant DB (current store).
 *
 * @extends RepositoryContract<TenantLanguage>
 */
interface TenantLanguageRepositoryContract extends RepositoryContract
{
    public function findByCode(string $code): ?TenantLanguage;

    /**
     * @return Collection<int, TenantLanguage>
     */
    public function ordered(): Collection;

    /**
     * @return list<string>
     */
    public function codes(): array;

    public function clearDefault(): void;

    public function nextSortOrder(): int;
}
