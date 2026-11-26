<?php

namespace App\Modules\Affiliates\DataTables;

use App\Modules\Affiliates\Repositories\Contracts\AffiliateRepositoryContract;
use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\Identity\Models\Affiliate;
use App\Shared\DataTables\BaseDataTable;
use App\Shared\Money\Money;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

/**
 * ADM-060 (Figma 74:66931): affiliate, type, clicks, conversions, earned, balance,
 * status, view / edit / pay out.
 *
 * @extends BaseDataTable<Affiliate>
 */
final class AffiliatesDataTable extends BaseDataTable
{
    public const FILTERS = ['search', 'type', 'status'];

    private bool $canManage = false;

    private bool $canPay = false;

    public function __construct(private readonly AffiliateRepositoryContract $affiliates) {}

    public function abilities(bool $canManage, bool $canPay): self
    {
        $this->canManage = $canManage;
        $this->canPay = $canPay;

        return $this;
    }

    protected function query(): Builder
    {
        return $this->affiliates->listing();
    }

    protected function filter(Builder $query, array $filters): Builder
    {
        return $this->affiliates->listing($filters);
    }

    protected function configure(EloquentDataTable $table): EloquentDataTable
    {
        $direction = static fn (string $order): string => $order === 'desc' ? 'desc' : 'asc';

        return $table
            ->filter(static fn (): null => null)
            ->orderColumn('affiliate', static fn (Builder $query, string $order) => $query->orderBy('name', $direction($order)))
            ->orderColumn('type', static fn (Builder $query, string $order) => $query->orderBy('type', $direction($order)))
            ->orderColumn('clicks', static fn (Builder $query, string $order) => $query->orderBy('clicks_count', $direction($order)))
            ->orderColumn('conversions', static fn (Builder $query, string $order) => $query->orderBy('conversions_count', $direction($order)))
            ->orderColumn('earned', static fn (Builder $query, string $order) => $query->orderBy('total_earned', $direction($order)))
            ->orderColumn('balance', static fn (Builder $query, string $order) => $query->orderBy('balance', $direction($order)))
            ->orderColumn('status', static fn (Builder $query, string $order) => $query->orderBy('status', $direction($order)))
            ->addColumn('affiliate', static fn (Affiliate $affiliate): string => view('affiliates::admin.partials.person', [
                'title' => $affiliate->name,
                'subtitle' => $affiliate->email ?? __('affiliates::admin.accounts.auto_created'),
                'ltr' => $affiliate->email !== null,
            ])->render())
            ->addColumn('type', static fn (Affiliate $affiliate): string => view('affiliates::admin.partials.badge', ['tone' => $affiliate->type->tone(), 'label' => $affiliate->type->label()])->render())
            ->addColumn('clicks', static fn (Affiliate $affiliate): string => '<span dir="ltr">'.number_format((int) $affiliate->getAttribute('clicks_count')).'</span>')
            ->addColumn('conversions', static fn (Affiliate $affiliate): string => '<span dir="ltr">'.number_format((int) $affiliate->getAttribute('conversions_count')).'</span>')
            ->addColumn('earned', static fn (Affiliate $affiliate): string => '<span class="font-medium text-ink" dir="ltr">'.e(Money::ofMinor($affiliate->total_earned)->format()).'</span>')
            ->addColumn('balance', static fn (Affiliate $affiliate): string => '<span class="font-semibold text-ink" dir="ltr">'.e($affiliate->balanceMoney()->format()).'</span>')
            ->addColumn('status', static fn (Affiliate $affiliate): string => view('affiliates::admin.partials.badge', ['tone' => self::statusTone($affiliate->status), 'label' => $affiliate->status->label()])->render())
            ->addColumn('actions', fn (Affiliate $affiliate): string => view('affiliates::admin.accounts._actions', ['affiliate' => $affiliate, 'canManage' => $this->canManage, 'canPay' => $this->canPay])->render())
            ->rawColumns(['affiliate', 'type', 'clicks', 'conversions', 'earned', 'balance', 'status', 'actions']);
    }

    public static function statusTone(AccountStatus $status): string
    {
        return match ($status) {
            AccountStatus::Active => 'success',
            AccountStatus::Pending => 'warning',
            AccountStatus::Suspended => 'danger',
        };
    }
}
