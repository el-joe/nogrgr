<?php

namespace App\Modules\Affiliates\Repositories\Contracts;

use App\Modules\Identity\Models\Affiliate;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends RepositoryContract<Affiliate>
 */
interface AffiliateRepositoryContract extends RepositoryContract
{
    /**
     * Filters: search, type, status. Rows carry clicks_count / conversions_count.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<Affiliate>
     */
    public function listing(array $filters = []): Builder;

    /**
     * @return array{all: int, vendor: int, independent: int, pending: int, suspended: int}
     */
    public function counts(): array;

    public function findById(int $id): ?Affiliate;

    public function findActiveByCode(string $code): ?Affiliate;

    public function findForTenant(int $tenantId): ?Affiliate;

    public function lock(int $id): Affiliate;

    public function codeExists(string $code): bool;

    public function emailTaken(string $email): bool;

    /**
     * Select2 search: id => "Name (email)".
     *
     * @return array{results: list<array{id: int, text: string}>, pagination: array{more: bool}}
     */
    public function search(string $term, int $page = 1): array;
}
