<?php

namespace App\Modules\Reporting\Services;

use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Repositories\Contracts\TenantRepositoryContract;
use App\Shared\Services\BaseService;

/**
 * Store picker of the report / shopper filters (Select2 remote options) and the label
 * of the selected store.
 */
class StoreLookupService extends BaseService
{
    private const PER_PAGE = 20;

    public function __construct(private readonly TenantRepositoryContract $tenants) {}

    /**
     * @return array{results: list<array{id: int, text: string}>, pagination: array{more: bool}}
     */
    public function search(?string $term, int $page = 1): array
    {
        $term = trim((string) $term);
        $rows = $this->tenants->query()
            ->when($term !== '', static function ($query) use ($term): void {
                $like = '%'.addcslashes($term, '%_\\').'%';
                $query->where(static fn ($inner) => $inner->where('name', 'like', $like)->orWhere('slug', 'like', $like));
            })
            ->orderBy('name')
            ->forPage(max(1, $page), self::PER_PAGE + 1)
            ->get(['id', 'name', 'slug']);

        return [
            'results' => $rows->take(self::PER_PAGE)->map(static fn (Tenant $tenant): array => ['id' => (int) $tenant->id, 'text' => self::label($tenant)])->values()->all(),
            'pagination' => ['more' => $rows->count() > self::PER_PAGE],
        ];
    }

    /**
     * @return array<int, string> the selected store as a Select2 option
     */
    public function selected(?int $tenantId): array
    {
        $tenant = $tenantId !== null ? $this->tenants->find($tenantId) : null;

        return $tenant instanceof Tenant ? [(int) $tenant->id => self::label($tenant)] : [];
    }

    private static function label(Tenant $tenant): string
    {
        return $tenant->name.' ('.$tenant->slug.')';
    }
}
