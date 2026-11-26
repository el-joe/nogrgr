<?php

namespace App\Modules\Storefront\Repositories\Contracts;

use App\Modules\Storefront\Models\StorePage;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Store CMS pages (tenant DB).
 *
 * @extends RepositoryContract<StorePage>
 */
interface StorePageRepositoryContract extends RepositoryContract
{
    public function findBySlug(string $slug, bool $activeOnly = false): ?StorePage;

    /**
     * @return Collection<int, StorePage> active pages by sort order
     */
    public function published(bool $footerOnly = false): Collection;

    /**
     * @return list<string>
     */
    public function slugs(): array;

    /**
     * Vendor list (VEN-033): search in slug / any title, status (published | draft), type.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<StorePage>
     */
    public function listing(array $filters): Builder;
}
