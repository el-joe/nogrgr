<?php

namespace App\Modules\Reporting\Repositories\Eloquent;

use App\Modules\Reporting\DTOs\ReportFilterData;
use App\Modules\Reporting\Models\ProductDailyFact;
use App\Modules\Reporting\Repositories\Contracts\ProductActivityRepositoryContract;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * @extends BaseRepository<ProductDailyFact>
 */
class ProductActivityRepository extends BaseRepository implements ProductActivityRepositoryContract
{
    private const COUNTERS = ['views', 'cart_adds', 'orders', 'units', 'revenue', 'profit'];

    protected function model(): string
    {
        return ProductDailyFact::class;
    }

    public function increment(int $tenantId, int $storeProductId, string $day, ?string $productName, array $increments): void
    {
        $increments = array_filter(array_intersect_key($increments, array_flip(self::COUNTERS)), static fn ($value): bool => (int) $value !== 0);

        if ($increments === []) {
            return;
        }

        $now = now();
        $row = array_merge(array_fill_keys(self::COUNTERS, 0), array_map('intval', $increments), [
            'tenant_id' => $tenantId,
            'store_product_id' => $storeProductId,
            'day' => $day,
            'product_name' => $productName !== null ? mb_substr($productName, 0, 255) : null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $update = ['updated_at' => $now];

        foreach (array_keys($increments) as $counter) {
            $update[$counter] = DB::raw("{$counter} + ".(int) $increments[$counter]);
        }

        if ($productName !== null) {
            $update['product_name'] = $row['product_name'];
        }

        DB::connection((new ProductDailyFact)->getConnectionName())
            ->table('product_daily_facts')
            ->upsert([$row], ['tenant_id', 'store_product_id', 'day'], $update);
    }

    public function totals(ReportFilterData $filters): array
    {
        $row = $this->scoped($filters)
            ->selectRaw('COALESCE(SUM(views), 0) AS views, COALESCE(SUM(cart_adds), 0) AS cart_adds, COALESCE(SUM(orders), 0) AS orders, COALESCE(SUM(units), 0) AS units, COALESCE(SUM(revenue), 0) AS revenue')
            ->first();

        return array_map('intval', (array) $row) + array_fill_keys(self::COUNTERS, 0);
    }

    public function daily(ReportFilterData $filters): array
    {
        return $this->scoped($filters)
            ->selectRaw('day, SUM(views) AS views, SUM(cart_adds) AS cart_adds, SUM(units) AS units')
            ->groupBy('day')
            ->get()
            ->mapWithKeys(static fn (object $row): array => [substr((string) $row->day, 0, 10) => [
                'views' => (int) $row->views,
                'cart_adds' => (int) $row->cart_adds,
                'units' => (int) $row->units,
            ]])
            ->all();
    }

    public function byProduct(ReportFilterData $filters): QueryBuilder
    {
        $rows = $this->scoped($filters)
            ->selectRaw('tenant_id, store_product_id, MAX(product_name) AS product_name, SUM(views) AS views, SUM(cart_adds) AS cart_adds,'
                .' SUM(orders) AS orders, SUM(units) AS units, SUM(revenue) AS revenue')
            ->groupBy('tenant_id', 'store_product_id');

        return $this->central()->query()->fromSub($rows, 'p')
            ->join('tenants', 'tenants.id', '=', 'p.tenant_id')
            ->selectRaw('p.*, tenants.name AS tenant_name, tenants.slug AS tenant_slug');
    }

    private function scoped(ReportFilterData $filters): QueryBuilder
    {
        return $this->central()->table('product_daily_facts')
            ->whereBetween('day', [$filters->start()->toDateString(), $filters->end()->toDateString()])
            ->when($filters->tenantId !== null, static fn (QueryBuilder $query) => $query->where('tenant_id', $filters->tenantId));
    }

    /**
     * Reporting tables are central: queries must not follow the tenant connection that the
     * vendor panel switches to.
     */
    private function central(): ConnectionInterface
    {
        return DB::connection(config('tenancy.database.central_connection'));
    }
}
