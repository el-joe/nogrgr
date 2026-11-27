<?php

namespace App\Modules\Billing\Repositories\Eloquent;

use App\Modules\Billing\Enums\AddonPurchaseStatus;
use App\Modules\Billing\Enums\AddonType;
use App\Modules\Billing\Enums\PaymentGateway;
use App\Modules\Billing\Models\AddonPurchase;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\PaymentLog;
use App\Modules\Billing\Repositories\Contracts\AddonPurchaseRepositoryContract;
use App\Modules\Platform\Models\Tenant;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<AddonPurchase>
 */
class AddonPurchaseRepository extends BaseRepository implements AddonPurchaseRepositoryContract
{
    protected function model(): string
    {
        return AddonPurchase::class;
    }

    public function openFor(int $tenantId, AddonType $type, string $itemCode): ?AddonPurchase
    {
        return AddonPurchase::query()
            ->forTenant($tenantId)
            ->where('type', $type->value)
            ->where('item_code', $itemCode)
            ->where('status', AddonPurchaseStatus::PendingPayment->value)
            ->latest('id')
            ->first();
    }

    public function paidItemCodes(int $tenantId, AddonType $type): array
    {
        /** @var list<string> $codes */
        $codes = AddonPurchase::query()
            ->forTenant($tenantId)
            ->where('type', $type->value)
            ->where('status', AddonPurchaseStatus::Paid->value)
            ->distinct()
            ->pluck('item_code')
            ->map(static fn ($code): string => strtolower((string) $code))
            ->values()
            ->all();

        return $codes;
    }

    public function forTenant(int $tenantId): Collection
    {
        return AddonPurchase::query()->forTenant($tenantId)->latest('id')->get();
    }

    public function adminListing(array $filters = []): Builder
    {
        $type = AddonType::tryFrom(is_string($filters['type'] ?? null) ? $filters['type'] : '');
        $status = AddonPurchaseStatus::tryFrom(is_string($filters['status'] ?? null) ? $filters['status'] : '');
        $gateway = PaymentGateway::tryFrom(is_string($filters['gateway'] ?? null) ? $filters['gateway'] : '');
        $search = is_string($filters['search'] ?? null) && trim($filters['search']) !== '' ? trim($filters['search']) : null;

        $invoices = fn () => Invoice::query()->withoutGlobalScopes()
            ->whereColumn('invoices.billable_id', 'addon_purchases.id')
            ->where('invoices.billable_type', 'addon_purchase');

        return AddonPurchase::withoutTenantScope()
            ->select('addon_purchases.*')
            ->addSelect([
                'invoice_number' => $invoices()->select('invoices.number')->latest('invoices.id')->limit(1),
                'invoice_id' => $invoices()->select('invoices.id')->latest('invoices.id')->limit(1),
                'gateway' => PaymentLog::query()->withoutGlobalScopes()->select('payment_logs.gateway')
                    ->whereIn('payment_logs.invoice_id', $invoices()->select('invoices.id'))
                    ->latest('payment_logs.id')->limit(1),
            ])
            ->with('tenant')
            ->whereIn('addon_purchases.type', [AddonType::Language->value, AddonType::AiTranslation->value])
            ->when($type !== null, fn (Builder $query) => $query->where('addon_purchases.type', $type?->value))
            ->when($status !== null, fn (Builder $query) => $query->where('addon_purchases.status', $status?->value))
            ->when($gateway !== null, fn (Builder $query) => $query->whereExists(
                PaymentLog::query()->withoutGlobalScopes()->selectRaw('1')
                    ->where('payment_logs.gateway', $gateway?->value)
                    ->whereIn('payment_logs.invoice_id', $invoices()->select('invoices.id'))
                    ->toBase(),
            ))
            ->when($search !== null, function (Builder $query) use ($search, $invoices): void {
                $term = '%'.addcslashes((string) $search, '%_\\').'%';
                $query->where(fn (Builder $inner) => $inner
                    ->where('addon_purchases.item_code', 'like', $term)
                    ->orWhereIn('addon_purchases.tenant_id', Tenant::query()->select('id')->where('name', 'like', $term)->orWhere('slug', 'like', $term))
                    ->orWhereExists($invoices()->selectRaw('1')->where('invoices.number', 'like', $term)->toBase()));
            });
    }

    public function adminCount(): int
    {
        return AddonPurchase::withoutTenantScope()
            ->whereIn('type', [AddonType::Language->value, AddonType::AiTranslation->value])
            ->count();
    }

    public function findForAdmin(int $id): AddonPurchase
    {
        return AddonPurchase::withoutTenantScope()
            ->with(['tenant', 'invoices' => fn ($query) => $query->withoutGlobalScopes()->latest('id')])
            ->whereIn('type', [AddonType::Language->value, AddonType::AiTranslation->value])
            ->findOrFail($id);
    }

    public function paymentLogsFor(AddonPurchase $purchase): Collection
    {
        return PaymentLog::query()->withoutGlobalScopes()
            ->whereIn('invoice_id', Invoice::query()->withoutGlobalScopes()
                ->where('billable_type', 'addon_purchase')
                ->where('billable_id', $purchase->id)
                ->select('id'))
            ->with('invoice')
            ->latest('id')
            ->get();
    }
}
