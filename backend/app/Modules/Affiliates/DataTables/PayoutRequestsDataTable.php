<?php

namespace App\Modules\Affiliates\DataTables;

use App\Modules\Affiliates\Models\AffiliatePayoutRequest;
use App\Modules\Affiliates\Repositories\Contracts\PayoutRequestRepositoryContract;
use App\Shared\DataTables\BaseDataTable;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

/**
 * ADM-062 (Figma 74:67899): request + date, affiliate + payout method, amount, status,
 * details (pay out / reject when pending).
 *
 * @extends BaseDataTable<AffiliatePayoutRequest>
 */
final class PayoutRequestsDataTable extends BaseDataTable
{
    public const FILTERS = ['search', 'status'];

    public function __construct(private readonly PayoutRequestRepositoryContract $requests) {}

    protected function query(): Builder
    {
        return $this->requests->listing();
    }

    protected function filter(Builder $query, array $filters): Builder
    {
        return $this->requests->listing($filters);
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
            ->addColumn('affiliate', static fn (AffiliatePayoutRequest $request): string => view('affiliates::admin.partials.person', [
                'title' => $request->affiliate->name ?? '—',
                'subtitle' => $request->affiliate?->payout_method?->label() ?? __('affiliates::admin.payouts.no_method'),
            ])->render())
            ->addColumn('amount', static fn (AffiliatePayoutRequest $request): string => '<span class="font-semibold text-ink" dir="ltr">'.e($request->money()->format()).'</span>')
            ->addColumn('status', static fn (AffiliatePayoutRequest $request): string => view('affiliates::admin.partials.badge', ['tone' => $request->status->tone(), 'label' => $request->status->label()])->render())
            ->addColumn('actions', static fn (AffiliatePayoutRequest $request): string => view('affiliates::admin.payouts._actions', ['request' => $request])->render())
            ->rawColumns(['request', 'affiliate', 'amount', 'status', 'actions']);
    }
}
