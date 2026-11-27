<?php

namespace App\Modules\Storefront\Repositories\Contracts;

use App\Modules\Storefront\Models\CustomTheme;
use App\Modules\Storefront\Models\CustomThemeVersion;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Custom Twig themes and their versions (decision #121). Tenant-scoped in store context;
 * the admin review queue (central) reads across stores.
 *
 * @extends RepositoryContract<CustomTheme>
 */
interface CustomThemeRepositoryContract extends RepositoryContract
{
    public function forTenant(int $tenantId): ?CustomTheme;

    public function firstOrCreateForTenant(int $tenantId, string $name): CustomTheme;

    /**
     * Versions of a theme, newest first.
     *
     * @return Collection<int, CustomThemeVersion>
     */
    public function versions(CustomTheme $theme): Collection;

    /**
     * A version of the current store (store context) or of any store (central).
     */
    public function findVersion(int $id): ?CustomThemeVersion;

    /**
     * Approved versions of a store, newest first — the custom entries of the theme
     * catalogue (decision #119 seam for the admin / vendor theme lists).
     *
     * @return Collection<int, CustomThemeVersion>
     */
    public function approvedForTenant(int $tenantId): Collection;

    /**
     * The version the store's storefront renders (active theme with a live version), memoised per request.
     */
    public function liveVersionForTenant(int $tenantId): ?CustomThemeVersion;

    /**
     * The approved version published before this one (diff base), else the newest approved older version.
     */
    public function previousApproved(CustomThemeVersion $version): ?CustomThemeVersion;

    /**
     * Review queue rows across stores (admin, ADM-089).
     *
     * @return Builder<CustomThemeVersion>
     */
    public function reviewQuery(): Builder;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createVersion(CustomTheme $theme, array $attributes): CustomThemeVersion;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateVersion(CustomThemeVersion $version, array $attributes): CustomThemeVersion;

    public function deleteVersion(CustomThemeVersion $version): void;

    public function forget(): void;
}
