<?php

namespace App\Modules\Billing\Repositories\Eloquent;

use App\Modules\Billing\Enums\PaymentGateway;
use App\Modules\Billing\Enums\PaymentLogStatus;
use App\Modules\Billing\Models\PaymentLog;
use App\Modules\Billing\Repositories\Contracts\PaymentLogRepositoryContract;
use App\Modules\Platform\Models\Tenant;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends BaseRepository<PaymentLog>
 */
class PaymentLogRepository extends BaseRepository implements PaymentLogRepositoryContract
{
    protected function model(): string
    {
        return PaymentLog::class;
    }

    public function listing(array $filters = []): Builder
    {
        return $this->applyFilters(PaymentLog::query()->select('payment_logs.*')->with('tenant'), $filters);
    }

    public function findForDetail(int $id): PaymentLog
    {
        return PaymentLog::query()->with(['tenant', 'invoice'])->findOrFail($id);
    }

    public function findByGatewayReference(PaymentGateway $gateway, string $reference): ?PaymentLog
    {
        return PaymentLog::query()->withoutGlobalScopes()
            ->where('gateway', $gateway->value)
            ->where('gateway_reference', $reference)
            ->latest('id')
            ->first();
    }

    public function lockForUpdate(PaymentLog $log): PaymentLog
    {
        return PaymentLog::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($log->id);
    }

    protected function applyFilters(Builder $query, array $filters): Builder
    {
        $status = $filters['status'] ?? null;
        $gateway = $filters['gateway'] ?? null;
        $search = $filters['search'] ?? null;

        return $query
            ->when(is_string($status) && PaymentLogStatus::tryFrom($status) !== null, fn (Builder $q) => $q->where('payment_logs.status', $status))
            ->when(is_string($gateway) && PaymentGateway::tryFrom($gateway) !== null, fn (Builder $q) => $q->where('payment_logs.gateway', $gateway))
            ->when(is_string($search) && $search !== '', function (Builder $q) use ($search): void {
                $id = (int) preg_replace('/\D/', '', (string) $search);
                $term = '%'.addcslashes((string) $search, '%_\\').'%';
                $q->where(function (Builder $inner) use ($term, $id): void {
                    $inner->where('payment_logs.gateway_reference', 'like', $term)
                        ->orWhereIn('payment_logs.tenant_id', Tenant::query()->select('id')->where('name', 'like', $term));

                    if ($id > 0) {
                        $inner->orWhere('payment_logs.id', $id);
                    }
                });
            });
    }
}
