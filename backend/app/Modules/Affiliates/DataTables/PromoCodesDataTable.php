<?php

namespace App\Modules\Affiliates\DataTables;

use App\Modules\Affiliates\Models\AffiliatePromoCode;
use App\Modules\Affiliates\Repositories\Contracts\PromoCodeRepositoryContract;
use App\Shared\DataTables\BaseDataTable;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

/**
 * ADM-063 (Figma 74:67899): code + affiliate, discount, valid until, uses, status,
 * edit / delete.
 *
 * @extends BaseDataTable<AffiliatePromoCode>
 */
final class PromoCodesDataTable extends BaseDataTable
{
    public const FILTERS = ['search', 'state'];

    private bool $canManage = false;

    public function __construct(private readonly PromoCodeRepositoryContract $codes) {}

    public function abilities(bool $canManage): self
    {
        $this->canManage = $canManage;

        return $this;
    }

    protected function query(): Builder
    {
        return $this->codes->listing();
    }

    protected function filter(Builder $query, array $filters): Builder
    {
        return $this->codes->listing($filters);
    }

    protected function configure(EloquentDataTable $table): EloquentDataTable
    {
        $direction = static fn (string $order): string => $order === 'desc' ? 'desc' : 'asc';
        $locale = app()->getLocale();

        return $table
            ->filter(static fn (): null => null)
            ->orderColumn('code', static fn (Builder $query, string $order) => $query->orderBy('code', $direction($order)))
            ->orderColumn('discount', static fn (Builder $query, string $order) => $query->orderBy('discount_value', $direction($order)))
            ->orderColumn('ends_at', static fn (Builder $query, string $order) => $query->orderBy('ends_at', $direction($order)))
            ->orderColumn('uses', static fn (Builder $query, string $order) => $query->orderBy('uses', $direction($order)))
            ->addColumn('code', static fn (AffiliatePromoCode $code): string => view('affiliates::admin.partials.person', [
                'title' => $code->code,
                'subtitle' => $code->affiliate->name ?? '—',
            ])->render())
            ->addColumn('discount', static fn (AffiliatePromoCode $code): string => e(__('affiliates::admin.promo.discount_label', ['value' => $code->discountLabel()])))
            ->addColumn('ends_at', static fn (AffiliatePromoCode $code): string => e($code->ends_at?->locale($locale)->translatedFormat('j M Y') ?? '—'))
            ->addColumn('uses', static fn (AffiliatePromoCode $code): string => '<span class="font-semibold text-ink" dir="ltr">'.number_format($code->uses).($code->max_uses !== null ? ' / '.number_format($code->max_uses) : '').'</span>')
            ->addColumn('status', static fn (AffiliatePromoCode $code): string => view('affiliates::admin.partials.badge', [
                'tone' => $code->isUsable() ? 'success' : ($code->is_active ? 'warning' : 'neutral'),
                'label' => __('affiliates::admin.promo.states.'.($code->isUsable() ? 'active' : ($code->is_active ? 'expired' : 'inactive'))),
            ])->render())
            ->addColumn('actions', fn (AffiliatePromoCode $code): string => $this->canManage ? view('affiliates::admin.promo-codes._actions', ['code' => $code])->render() : '')
            ->rawColumns(['code', 'uses', 'status', 'actions']);
    }
}
