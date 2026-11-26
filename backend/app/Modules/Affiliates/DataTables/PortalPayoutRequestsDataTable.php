<?php

namespace App\Modules\Affiliates\DataTables;

use App\Modules\Affiliates\Models\AffiliatePayoutRequest;
use App\Modules\Affiliates\Repositories\Contracts\PayoutRequestRepositoryContract;
use App\Modules\Identity\Models\Affiliate;
use App\Shared\DataTables\BaseDataTable;
use Illuminate\Database\Eloquent\Builder;
use LogicException;
use Yajra\DataTables\EloquentDataTable;

/**
 * The affiliate's own payout requests (AFF-012): reference + date, amount, status and
 * the admin's note on a rejection.
 *
 * @extends BaseDataTable<AffiliatePayoutRequest>
 */
final class PortalPayoutRequestsDataTable extends BaseDataTable
{
    public const FILTERS = ['status'];

    private ?Affiliate $affiliate = null;

    public function __construct(private readonly PayoutRequestRepositoryContract $requests) {}

    public function for(Affiliate $affiliate): self
    {
        $this->affiliate = $affiliate;

        return $this;
    }

    protected function query(): Builder
    {
        if ($this->affiliate === null) {
            throw new LogicException('The payout requests table needs its affiliate.');
        }

        return $this->requests->listing()->where('affiliate_id', $this->affiliate->id);
    }

    protected function filter(Builder $query, array $filters): Builder
    {
        return $query->when(filled($filters['status'] ?? null), static fn (Builder $q) => $q->where('status', (string) $filters['status']));
    }

    protected function configure(EloquentDataTable $table): EloquentDataTable
    {
        $direction = static fn (string $order): string => $order === 'desc' ? 'desc' : 'asc';
        $locale = app()->getLocale();

        return $table
            ->filter(static fn (): null => null)
            ->orderColumn('request', static fn (Builder $query, string $order) => $query->orderBy('created_at', $direction($order))->orderBy('id', $direction($order)))
            ->orderColumn('amount', static fn (Builder $query, string $order) => $query->orderBy('amount', $direction($order)))
            ->orderColumn('status', static fn (Builder $query, string $order) => $query->orderBy('status', $direction($order)))
            ->addColumn('request', static fn (AffiliatePayoutRequest $request): string => view('affiliates::admin.partials.person', [
                'title' => $request->reference(),
                'subtitle' => $request->created_at?->locale($locale)->translatedFormat('j M Y'),
            ])->render())
            ->addColumn('amount', static fn (AffiliatePayoutRequest $request): string => '<span class="font-semibold text-ink" dir="ltr">'.e($request->money()->format()).'</span>')
            ->addColumn('status', static fn (AffiliatePayoutRequest $request): string => view('affiliates::admin.partials.badge', ['tone' => $request->status->tone(), 'label' => $request->status->label()])->render())
            ->addColumn('note', static fn (AffiliatePayoutRequest $request): string => '<span class="text-xs text-ink-secondary">'.e($request->admin_note ?? $request->note ?? '—').'</span>')
            ->rawColumns(['request', 'amount', 'status', 'note']);
    }
}
