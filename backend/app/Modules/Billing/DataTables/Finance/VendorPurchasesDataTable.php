<?php

namespace App\Modules\Billing\DataTables\Finance;

use App\Modules\Billing\Enums\LedgerAccount;
use App\Modules\Billing\Models\LedgerEntry;
use App\Shared\DataTables\BaseDataTable;
use App\Shared\Money\Money;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

/**
 * Purchases owed to the platform (VEN-062, Figma 88:111108): the store's orders whose
 * platform share is still open in the ledger (vendor_receivable per order), oldest
 * first, with "Pay platform" (VEN-021d).
 *
 * @extends BaseDataTable<LedgerEntry>
 */
final class VendorPurchasesDataTable extends BaseDataTable
{
    public const FILTERS = ['search'];

    private int $tenantId = 0;

    private bool $canSettle = false;

    public function forTenant(int $tenantId, bool $canSettle): self
    {
        $this->tenantId = $tenantId;
        $this->canSettle = $canSettle;

        return $this;
    }

    /**
     * @return Builder<LedgerEntry>
     */
    public static function owedQuery(int $tenantId, ?string $search = null): Builder
    {
        $grouped = LedgerEntry::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->where('account', LedgerAccount::VendorReceivable->value)
            ->whereNotNull('order_uuid')
            ->groupBy('order_uuid')
            ->selectRaw('MIN(id) as id, order_uuid, MAX(order_number) as order_number, SUM(debit) as accrued, SUM(credit) as settled, SUM(debit) - SUM(credit) as owed, MIN(created_at) as created_at');

        $search = trim((string) $search);

        return LedgerEntry::withoutTenantScope()
            ->fromSub($grouped->toBase(), 'ledger_entries')
            ->where('owed', '>', 0)
            ->when($search !== '', static fn (Builder $q) => $q->where('order_number', 'like', '%'.ltrim($search, '#').'%'));
    }

    protected function query(): Builder
    {
        return self::owedQuery($this->tenantId);
    }

    protected function filter(Builder $query, array $filters): Builder
    {
        return self::owedQuery($this->tenantId, is_string($filters['search'] ?? null) ? $filters['search'] : null);
    }

    protected function configure(EloquentDataTable $table): EloquentDataTable
    {
        $direction = static fn (string $order): string => $order === 'desc' ? 'desc' : 'asc';

        return $table
            ->filter(static fn (): null => null)
            ->orderColumn('order', static fn (Builder $query, string $order) => $query->orderBy('created_at', $direction($order)))
            ->orderColumn('owed', static fn (Builder $query, string $order) => $query->orderBy('owed', $direction($order)))
            ->addColumn('order', fn (LedgerEntry $row): string => '<span class="block font-medium text-ink" dir="ltr">#'.e((string) $row->order_number).'</span><span class="text-xs text-ink-secondary">'.e((string) $row->created_at?->locale(app()->getLocale())->translatedFormat('j M Y')).'</span>')
            ->addColumn('accrued', fn (LedgerEntry $row): string => '<span dir="ltr">'.e(Money::ofMinor((int) $row->getAttribute('accrued'))->format()).'</span>')
            ->addColumn('settled', fn (LedgerEntry $row): string => '<span dir="ltr">'.e(Money::ofMinor((int) $row->getAttribute('settled'))->format()).'</span>')
            ->addColumn('owed', fn (LedgerEntry $row): string => '<span class="font-semibold text-ink" dir="ltr">'.e(Money::ofMinor((int) $row->getAttribute('owed'))->format()).'</span>')
            ->addColumn('actions', fn (LedgerEntry $row): string => $this->canSettle
                ? '<div class="flex justify-end">'.view('billing::vendor.finance._settle-link', ['uuid' => (string) $row->order_uuid])->render().'</div>'
                : '')
            ->rawColumns(['order', 'accrued', 'settled', 'owed', 'actions']);
    }
}
