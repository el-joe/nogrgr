<?php

namespace App\Modules\Reporting\Repositories\Eloquent;

use App\Modules\Reporting\Models\OrderFact;
use App\Modules\Reporting\Repositories\Contracts\OrderFactRepositoryContract;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends BaseRepository<OrderFact>
 */
class OrderFactRepository extends BaseRepository implements OrderFactRepositoryContract
{
    protected function model(): string
    {
        return OrderFact::class;
    }

    public function upsertFor(int $tenantId, string $orderUuid, array $attributes): OrderFact
    {
        /** @var OrderFact $fact */
        $fact = OrderFact::withoutTenantScope()->updateOrCreate(
            ['tenant_id' => $tenantId, 'order_uuid' => $orderUuid],
            $attributes,
        );

        return $fact;
    }

    /**
     * Statuses of a queue tab (mirrors Commerce FulfilmentStatus::queueTab()).
     */
    public const QUEUE_TABS = [
        'to_ship' => ['pending', 'packed'],
        'in_transit' => ['in_transit', 'out_for_delivery'],
        'delayed' => ['delayed'],
        'delivered' => ['delivered'],
    ];

    private const QUEUE_ORDER_STATUSES = ['paid', 'processing', 'shipped', 'delivered', 'completed'];

    public function listing(array $filters): Builder
    {
        $status = is_string($filters['status'] ?? null) && $filters['status'] !== '' ? $filters['status'] : null;
        $payment = is_string($filters['payment'] ?? null) && $filters['payment'] !== '' ? $filters['payment'] : null;
        $gateway = is_string($filters['gateway'] ?? null) && $filters['gateway'] !== '' ? $filters['gateway'] : null;
        $tenant = filter_var($filters['tenant_id'] ?? null, FILTER_VALIDATE_INT);
        $country = filter_var($filters['country_id'] ?? null, FILTER_VALIDATE_INT);

        return $this->base($filters)
            ->where('status', '!=', 'pending_payment')
            ->when($status !== null, static fn (Builder $query) => $query->where('status', $status))
            ->when($payment !== null, static fn (Builder $query) => $query->where('payment_status', $payment))
            ->when($gateway !== null, static fn (Builder $query) => $query->where('payment_gateway', $gateway))
            ->when(is_int($tenant), static fn (Builder $query) => $query->where('tenant_id', $tenant))
            ->when(is_int($country), static fn (Builder $query) => $query->where('country_id', $country));
    }

    public function shippingQueue(array $filters): Builder
    {
        $tab = is_string($filters['tab'] ?? null) && array_key_exists($filters['tab'], self::QUEUE_TABS) ? $filters['tab'] : 'to_ship';
        $company = filter_var($filters['company_id'] ?? null, FILTER_VALIDATE_INT);
        $branch = filter_var($filters['branch_id'] ?? null, FILTER_VALIDATE_INT);
        $country = filter_var($filters['country_id'] ?? null, FILTER_VALIDATE_INT);
        $status = is_string($filters['fulfilment_status'] ?? null) && $filters['fulfilment_status'] !== '' ? $filters['fulfilment_status'] : null;

        return $this->base($filters)
            ->where('kind', '!=', 'own')
            ->whereIn('status', self::QUEUE_ORDER_STATUSES)
            ->whereIn('fulfilment_status', self::QUEUE_TABS[$tab])
            ->when($tab === 'delivered', static fn (Builder $query) => $query->whereDate('delivered_at', today()))
            ->when($status !== null, static fn (Builder $query) => $query->where('fulfilment_status', $status))
            ->when(is_int($company), static fn (Builder $query) => $query->where('shipping_company_id', $company))
            ->when(is_int($branch), static fn (Builder $query) => $query->where('fulfilment_branch_id', $branch))
            ->when(is_int($country), static fn (Builder $query) => $query->where('country_id', $country));
    }

    public function statusCounts(): array
    {
        /** @var array<string, int> $counts */
        $counts = $this->query()->where('status', '!=', 'pending_payment')->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status')->map(static fn (mixed $n): int => (int) $n)->all();

        return $counts;
    }

    public function queueCounts(): array
    {
        $counts = [];

        foreach (array_keys(self::QUEUE_TABS) as $tab) {
            $counts[$tab] = $this->shippingQueue(['tab' => $tab])->count();
        }

        return $counts;
    }

    public function findFact(int $id): ?OrderFact
    {
        return $this->query()->with('tenant')->find($id);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<OrderFact>
     */
    private function base(array $filters): Builder
    {
        $search = is_string($filters['search'] ?? null) ? trim($filters['search']) : '';
        $from = is_string($filters['from'] ?? null) && strtotime($filters['from']) !== false ? $filters['from'] : null;
        $to = is_string($filters['to'] ?? null) && strtotime($filters['to']) !== false ? $filters['to'] : null;

        return $this->query()
            ->with('tenant')
            ->when($from !== null, static fn (Builder $query) => $query->whereDate('placed_at', '>=', (string) $from))
            ->when($to !== null, static fn (Builder $query) => $query->whereDate('placed_at', '<=', (string) $to))
            ->when($search !== '', static function (Builder $query) use ($search): void {
                $term = '%'.addcslashes($search, '%_\\').'%';
                $query->where(static fn (Builder $inner) => $inner
                    ->where('order_number', 'like', $term)
                    ->orWhere('customer_name', 'like', $term)
                    ->orWhere('tracking_number', 'like', $term));
            });
    }
}
