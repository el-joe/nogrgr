<?php

namespace App\Modules\Billing\Services;

use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Repositories\Contracts\TenantRepositoryContract;
use App\Shared\Services\BaseService;

/**
 * Store picker for billing forms (Select2 remote options).
 */
class TenantLookupService extends BaseService
{
    private const PER_PAGE = 20;

    public function __construct(private readonly TenantRepositoryContract $tenants) {}

    /**
     * @return array{results: list<array{id: int, text: string}>, pagination: array{more: bool}}
     */
    public function search(?string $term, int $page = 1): array
    {
        $page = max(1, $page);
        $term = trim((string) $term);

        $rows = $this->tenants->query()
            ->when($term !== '', function ($query) use ($term): void {
                $like = '%'.addcslashes($term, '%_\\').'%';
                $query->where(fn ($inner) => $inner->where('name', 'like', $like)->orWhere('slug', 'like', $like));
            })
            ->orderBy('name')
            ->forPage($page, self::PER_PAGE + 1)
            ->get(['id', 'name', 'slug']);

        return [
            'results' => $rows->take(self::PER_PAGE)->map(fn (Tenant $tenant): array => [
                'id' => (int) $tenant->id,
                'text' => $tenant->name.' ('.$tenant->slug.')',
            ])->values()->all(),
            'pagination' => ['more' => $rows->count() > self::PER_PAGE],
        ];
    }
}
